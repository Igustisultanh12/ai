<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Services\Agent\TokenomixClient;
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

    public function __construct(TokenomixClient $client, TokenAccountingService $accounting, AuditService $auditService)
    {
        $this->client = $client;
        $this->accounting = $accounting;
        $this->auditService = $auditService;
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

        $activeConfig = AgentConfig::where('is_active', true)->first() ?: AgentConfig::first();
        $model = $validated['model'] ?: ($activeConfig?->default_model ?: 'glm-5.1');

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'project_id' => $validated['project_id'] ?? null,
            'title' => $validated['title'] ?: 'Percakapan Baru',
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
            'title' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string'],
        ]);

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

        $selectedModel = $validated['model'] ?: $conversation->model;

        // Check user quota
        if (!$this->accounting->checkQuota($user, 1000, $selectedModel)) {
            return response()->json([
                'message' => 'Batas kuota token Anda telah tercapai.',
            ], 429);
        }

        $streamId = $validated['stream_id'] ?: (string) Str::uuid();
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

        $messagesHistory = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content ?? ''])
            ->toArray();

        $idempotencyKey = "stream_msg_{$assistantMsg->id}_" . Str::random(6);

        return response()->stream(function () use (
            $user,
            $conversation,
            $assistantMsg,
            $messagesHistory,
            $selectedModel,
            $idempotencyKey,
            $streamId
        ) {
            // Disable PHP output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $fullContent = '';
            $reportedUsage = null;
            $stopRequestedKey = "stream_stop:{$conversation->id}";

            try {
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
                    // Check if client requested stop
                    if (Cache::has($stopRequestedKey)) {
                        echo "data: " . json_encode(['event' => 'stopped', 'message' => 'Generasi dibatalkan oleh pengguna.']) . "\n\n";
                        flush();
                        return;
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
        });
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
