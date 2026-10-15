<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Services\Agent\ModelCatalogService;
use App\Services\Agent\TokenomixClient;
use App\Services\GitHub\GitHubService;
use App\Services\Auth\AuditService;
use App\Services\Usage\TokenAccountingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    protected TokenomixClient $client;
    protected TokenAccountingService $accounting;
    protected AuditService $auditService;
    protected ModelCatalogService $catalog;

    /**
     * Default instructions for chat. Asking the model to label code fences with a
     * file path lets the UI offer a one-click "Push ke GitHub" for those files.
     */
    public const CHAT_SYSTEM_PROMPT = <<<'PROMPT'
Kamu adalah asisten pemrograman AI XZY. Jawab dalam bahasa yang dipakai pengguna.
Jika kamu menulis isi sebuah berkas kode, beri label path relatif berkas tersebut pada pembuka blok kode dengan format ```bahasa:path/ke/berkas.ext (contoh: ```php:app/Http/Controllers/UserController.php), dan tulis isi berkas secara lengkap, bukan potongan, agar pengguna bisa langsung push ke GitHub dari chat.
Untuk potongan kode contoh yang bukan berkas utuh, gunakan blok kode biasa tanpa path.
PROMPT;

    public function __construct(
        TokenomixClient $client,
        TokenAccountingService $accounting,
        AuditService $auditService,
        ModelCatalogService $catalog
    ) {
        $this->client = $client;
        $this->accounting = $accounting;
        $this->auditService = $auditService;
        $this->catalog = $catalog;
    }

    /**
     * List user conversations (optionally filtered by project).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Conversation::where('user_id', $user->id);

        if ($request->has('project_id') && !empty($request->project_id)) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('search') && !empty($request->search)) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $conversations = $query->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        if (!empty($validated['project_id'])) {
            $project = Project::where('id', $validated['project_id'])->where('user_id', $user->id)->firstOrFail();
        }

        $model = $this->resolveModel($validated['model'] ?? null);
        if ($model instanceof JsonResponse) {
            return $model;
        }

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'project_id' => $validated['project_id'] ?? null,
            'title' => (!empty($validated['title'])) ? $validated['title'] : 'Percakapan Baru',
            'model' => $model,
        ]);

        return response()->json([
            'message' => 'Percakapan berhasil dibuat.',
            'conversation' => $conversation,
        ], 201);
    }

    /**
     * Show a conversation with messages.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $conversation = $this->getAuthorizedConversation($request, $id);

        return response()->json([
            'conversation' => $conversation->load('messages'),
        ]);
    }

    /**
     * Rename conversation title.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $conversation = $this->getAuthorizedConversation($request, $id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if (array_key_exists('model', $validated)) {
            $model = $this->resolveModel($validated['model']);
            if ($model instanceof JsonResponse) {
                return $model;
            }
            $validated['model'] = $model;
        }

        $conversation->update($validated);

        return response()->json([
            'message' => 'Percakapan berhasil diperbarui.',
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete conversation.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $conversation = $this->getAuthorizedConversation($request, $id);
        $conversation->delete();

        return response()->json(['message' => 'Percakapan berhasil dihapus.']);
    }

    /**
     * Post a user message and stream assistant response via SSE with reconnect idempotency.
     */
    public function streamMessage(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $conversation = $this->getAuthorizedConversation($request, $id);
        $user = $request->user();

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'model' => ['nullable', 'string'],
            'stream_id' => ['nullable', 'string'], // Reconnect client identifier
        ]);

        $selectedModel = $this->resolveModel(!empty($validated['model']) ? $validated['model'] : $conversation->model, strict: !empty($validated['model']));
        if ($selectedModel instanceof JsonResponse) {
            return $selectedModel;
        }

        // Remember the user's model choice for this conversation
        if ($conversation->model !== $selectedModel) {
            $conversation->update(['model' => $selectedModel]);
        }

        // Check user quota
        if (!$this->accounting->checkQuota($user, 1000, $selectedModel)) {
            return response()->json([
                'message' => 'Batas kuota token Anda telah tercapai.',
            ], 429);
        }

        $streamId = (!empty($validated['stream_id'])) ? $validated['stream_id'] : (string) Str::uuid();
        $cacheLockKey = "stream_lock:{$streamId}";

        // Prevent duplicate upstream request on client reconnect
        if (Cache::has($cacheLockKey)) {
            $existingMessage = Message::where('conversation_id', $conversation->id)
                ->where('role', 'assistant')
                ->latest()
                ->first();

            return response()->json([
                'message' => 'Permintaan streaming ini sudah aktif atau telah selesai.',
                'stream_id' => $streamId,
                'assistant_message' => $existingMessage,
            ]);
        }

        Cache::put($cacheLockKey, 'active', now()->addMinutes(5));

        // Save user message
        $userMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['content'],
            'status' => 'completed',
        ]);

        // Auto-update conversation title if it's the first message
        if ($conversation->messages()->count() <= 2 && $conversation->title === 'Percakapan Baru') {
            $conversation->update(['title' => mb_substr($validated['content'], 0, 40) . '...']);
        }

        // Initialize assistant message record with status streaming
        $assistantMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => '',
            'status' => 'streaming',
        ]);

        // History for the model: skip the empty placeholder we just created and failed/empty turns
        $messagesHistory = $conversation->messages()
            ->where('id', '!=', $assistantMsg->id)
            ->whereIn('role', ['user', 'assistant'])
            ->where('status', '!=', 'failed')
            ->get()
            ->filter(fn ($m) => trim((string) $m->content) !== '')
            ->map(fn ($m) => ['role' => $m->role, 'content' => (string) $m->content])
            ->values()
            ->toArray();

        $systemPrompt = trim(self::CHAT_SYSTEM_PROMPT . "\n\n" . (string) $conversation->system_prompt);
        array_unshift($messagesHistory, ['role' => 'system', 'content' => $systemPrompt]);

        $idempotencyKey = "stream_msg_{$assistantMsg->id}_" . Str::random(6);
        $userMsgId = $userMsg->id;

        return response()->stream(function () use (
            $user,
            $conversation,
            $assistantMsg,
            $messagesHistory,
            $selectedModel,
            $idempotencyKey,
            $streamId,
            $userMsgId
        ) {
            ignore_user_abort(true);
            set_time_limit(0);

            // Disable PHP output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            $fullContent = '';
            $reportedUsage = null;
            $stopRequestedKey = "stream_stop:{$conversation->id}";

            try {
                echo "data: " . json_encode([
                    'event' => 'start',
                    'model' => $selectedModel,
                    'user_message_id' => $userMsgId,
                    'assistant_message_id' => $assistantMsg->id,
                    'stream_id' => $streamId,
                ]) . "\n\n";
                flush();

                $payload = [
                    'model' => $selectedModel,
                    'messages' => $messagesHistory,
                    'temperature' => 0.7,
                ];

                $this->client->streamChatCompletion($payload, function ($chunkText, $isDone, $usage) use (
                    &$fullContent,
                    &$reportedUsage,
                    $assistantMsg,
                    $stopRequestedKey
                ) {
                    // Check if client requested stop: tell the client and abort upstream
                    if (Cache::has($stopRequestedKey)) {
                        echo "data: " . json_encode(['event' => 'stopped', 'message' => 'Generasi dibatalkan oleh pengguna.']) . "\n\n";
                        flush();
                        return false;
                    }

                    if ($usage) {
                        $reportedUsage = $usage;
                    }

                    if ($isDone) {
                        echo "data: [DONE]\n\n";
                        flush();
                    } else {
                        $fullContent .= $chunkText;
                        echo "data: " . json_encode(['content' => $chunkText]) . "\n\n";
                        flush();
                    }
                });

                // Update assistant message
                $assistantMsg->update([
                    'content' => $fullContent,
                    'status' => Cache::has($stopRequestedKey) ? 'cancelled' : 'completed',
                    'prompt_tokens' => $reportedUsage['prompt_tokens'] ?? null,
                    'completion_tokens' => $reportedUsage['completion_tokens'] ?? null,
                ]);

                // Record Token Accounting
                $pTokens = $reportedUsage['prompt_tokens'] ?? $this->accounting->estimateTokenCount(json_encode($messagesHistory));
                $cTokens = $reportedUsage['completion_tokens'] ?? $this->accounting->estimateTokenCount($fullContent);
                $dataSource = $reportedUsage ? 'actual' : 'estimated';

                $this->accounting->recordUsage(
                    user: $user,
                    model: $selectedModel,
                    idempotencyKey: $idempotencyKey,
                    promptTokens: $pTokens,
                    completionTokens: $cTokens,
                    dataSource: $dataSource,
                    status: Cache::has($stopRequestedKey) ? 'cancelled' : 'success',
                    projectId: $conversation->project_id,
                    conversationId: (string) $conversation->id
                );

            } catch (Exception $e) {
                $assistantMsg->update([
                    'content' => $fullContent . "\n\n[Kesalahan Streaming]: " . $e->getMessage(),
                    'status' => 'failed',
                ]);

                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                flush();
            } finally {
                Cache::forget($stopRequestedKey);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Push files written in the chat straight to a GitHub repository branch.
     */
    public function pushToGitHub(Request $request, string $id, GitHubService $github): JsonResponse
    {
        $conversation = $this->getAuthorizedConversation($request, $id);
        $user = $request->user();

        $validated = $request->validate([
            'repo' => ['required', 'string', 'max:200', 'regex:#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#'],
            'branch' => ['required', 'string', 'max:100'],
            'commit_message' => ['nullable', 'string', 'max:500'],
            'files' => ['required', 'array', 'min:1', 'max:100'],
            'files.*.path' => ['required', 'string', 'max:400'],
            'files.*.content' => ['present', 'nullable', 'string', 'max:1000000'],
        ]);

        $files = [];
        foreach ($validated['files'] as $file) {
            $path = $github->sanitizePath($file['path']);
            if ($path === null) {
                return response()->json(['message' => "Path berkas tidak valid: {$file['path']}"], 422);
            }
            $content = (string) ($file['content'] ?? '');
            if ($content !== '' && !str_ends_with($content, "\n")) {
                $content .= "\n";
            }
            $files[$path] = $content; // later duplicates win
        }

        $commitMessage = !empty($validated['commit_message'])
            ? $validated['commit_message']
            : 'Update dari chat XZY: ' . mb_substr($conversation->title ?: 'percakapan', 0, 60);

        try {
            $result = $github->pushFiles($user, $validated['repo'], $validated['branch'], $files, $commitMessage, [
                'audit_type' => 'Conversation',
                'audit_id' => (string) $conversation->id,
            ]);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Validate a requested model against the TokenomixAI catalog.
     * Non-strict mode silently falls back to the default (e.g. stale model on an old conversation).
     */
    protected function resolveModel(?string $model, bool $strict = true): string|JsonResponse
    {
        if (empty($model)) {
            return $this->catalog->defaultModel();
        }

        if ($this->catalog->isAvailable($model)) {
            return $model;
        }

        if (!$strict) {
            return $this->catalog->defaultModel();
        }

        return response()->json([
            'message' => "Model {$model} tidak tersedia di katalog TokenomixAI. Pilih model lain.",
            'errors' => ['model' => ['Model tidak tersedia.']],
        ], 422);
    }

    /**
     * Stop ongoing generation.
     */
    public function stopGeneration(Request $request, string $id): JsonResponse
    {
        $conversation = $this->getAuthorizedConversation($request, $id);
        Cache::put("stream_stop:{$conversation->id}", true, now()->addMinutes(1));

        return response()->json([
            'message' => 'Permintaan pembatalan generasi dikirim.',
        ]);
    }

    protected function getAuthorizedConversation(Request $request, string $id): Conversation
    {
        $user = $request->user();
        $conversation = Conversation::where('id', $id)->firstOrFail();

        if ($conversation->user_id !== $user->id) {
            abort(403, 'Akses percakapan ditolak.');
        }

        return $conversation;
    }
}
