<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Exceptions\UpstreamRetryableException;
use App\Models\ModelMultiplier;
use App\Services\Agent\ChatToolbox;
use App\Services\Agent\ModelCatalogService;
use App\Services\Agent\TokenomixClient;
use App\Services\GitHub\GitHubService;
use App\Services\GitHub\PushCommandParser;
use App\Support\CodeBlockExtractor;
use App\Services\Auth\AuditService;
use App\Services\Usage\TokenAccountingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
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
    public const MAX_UPSTREAM_ATTEMPTS = 4;

    public const CHAT_SYSTEM_PROMPT = <<<'PROMPT'
Kamu adalah asisten pemrograman AI XZY. Jawab dalam bahasa yang dipakai pengguna.
Gaya jawaban: rapi dengan markdown, tanpa emoji atau emotikon.
Jika kamu menulis isi sebuah berkas kode, beri label path relatif berkas tersebut pada pembuka blok kode dengan format ```bahasa:path/ke/berkas.ext (contoh: ```php:app/Http/Controllers/UserController.php), dan tulis isi berkas secara lengkap, bukan potongan, agar pengguna bisa langsung push ke GitHub dari chat.
Untuk potongan kode contoh yang bukan berkas utuh, gunakan blok kode biasa tanpa path.

Kamu punya tool untuk GitHub dan proyek XZY, dan kamu BISA membaca repo, clone, mengubah kode, commit, dan push memakai token GitHub pengguna. Jangan menyuruh pengguna clone/copy-paste manual; kerjakan sendiri dengan tool:
- Melihat/memperbaiki repo: github_list_files -> github_read_files (berkas yang relevan saja) -> tulis ulang berkas lengkap -> github_push_files.
- Jika pengguna meminta mengubah/memperbaiki repo, perubahan itu memang untuk di-push ke repo tersebut (gunakan branch yang disebut pengguna, default branch jika tidak disebut). Jika pengguna hanya bertanya atau minta pratinjau, jangan push.
- "Clone ke proyek": clone_repo_to_project, lalu project_read_files / project_write_files / project_push_to_github.
- Membuat repo baru hanya jika diminta (github_create_repo). Jika gagal (misalnya HTTP 404/403 karena izin token), jangan coba ulang dan jangan lanjut menulis kode: sampaikan pesan error tool apa adanya, termasuk cara memperbaiki izin token atau membuat repo manual lalu mengirim link-nya.
- Tulis isi berkas secara lengkap di argumen tool, jangan potongan atau placeholder. Pertahankan kode lain yang tidak diminta berubah.
- Kerjakan perubahan besar secara bertahap: maksimal sekitar 3 berkas (kira-kira 40 KB) per pemanggilan github_push_files/project_write_files, lalu lanjutkan dengan commit berikutnya. Jawaban yang terlalu panjang rawan gagal di gateway.
- Baca hanya berkas yang relevan dengan permintaan; jangan membaca seluruh repo.
- Jika github_list_files mengembalikan "can_push": false, JANGAN menulis berkas: beri tahu pengguna bahwa token GitHub-nya belum punya izin menulis ke repo itu dan cara memperbaikinya (dari pesan error tool). Jika tool push gagal karena izin token, jangan coba ulang dengan format URL lain; jelaskan penyebab sebenarnya.
- Jangan pernah mengaku sudah push/clone tanpa hasil tool yang sukses. Setelah push, sebutkan repo, branch, dan link commit dari hasil tool. Jika tool gagal, jelaskan penyebabnya apa adanya.
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
            'project_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        if (!empty($validated['project_id'])) {
            Project::where('id', $validated['project_id'])->where('user_id', $request->user()->id)->firstOrFail();
        }

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

        // "push ke https://github.com/user/repo" is executed by the server, not the model
        $pushCommand = app(PushCommandParser::class)->parse($validated['content']);
        if ($pushCommand && $pushCommand['push_only']) {
            return $this->streamPushCommand($conversation, $user, $validated['content'], $pushCommand);
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

        $systemPrompt = trim(self::CHAT_SYSTEM_PROMPT . "\n\n" . $this->runtimeContext($conversation, $user) . "\n\n" . (string) $conversation->system_prompt);
        array_unshift($messagesHistory, ['role' => 'system', 'content' => $systemPrompt]);

        $idempotencyKey = "stream_msg_{$assistantMsg->id}_" . Str::random(6);
        $userMsgId = $userMsg->id;

        $toolbox = app(ChatToolbox::class);
        $modelInfo = ModelMultiplier::where('model_id', $selectedModel)->first();
        $toolsEnabled = $modelInfo ? (bool) $modelInfo->supports_tools : true;

        return response()->stream(function () use (
            $user,
            $conversation,
            $assistantMsg,
            $messagesHistory,
            $selectedModel,
            $idempotencyKey,
            $streamId,
            $userMsgId,
            $toolbox,
            $toolsEnabled
        ) {
            ignore_user_abort(true);
            set_time_limit(0);

            // Disable PHP output buffering so events reach the browser immediately
            if (ob_get_level() && !app()->runningUnitTests()) {
                ob_end_clean();
            }

            $lastOutput = microtime(true);
            $send = function (array|string $data) use (&$lastOutput) {
                echo 'data: ' . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "\n\n";
                flush();
                $lastOutput = microtime(true);
            };

            // Keep proxies (Cloudflare ~100s, nginx/PHP-FPM ~60s) from closing a quiet stream:
            // an SSE comment line is ignored by the browser but counts as traffic.
            $heartbeat = function () use (&$lastOutput) {
                if (microtime(true) - $lastOutput >= 5) {
                    echo ": ping\n\n";
                    flush();
                    $lastOutput = microtime(true);
                }
            };
            // Every GitHub API response during tool execution also ticks the heartbeat
            Event::listen(ResponseReceived::class, fn () => $heartbeat());

            $lastProgress = 0.0;
            $onTick = function (int $toolChars, ?string $toolName) use (&$lastProgress, $heartbeat, $send) {
                if ($toolChars > 0 && microtime(true) - $lastProgress >= 2) {
                    $lastProgress = microtime(true);
                    $send(['event' => 'progress', 'tool' => $toolName, 'chars' => $toolChars]);
                    return;
                }
                $heartbeat();
            };

            $fullContent = '';
            $activity = [];
            $promptTotal = 0;
            $completionTotal = 0;
            $usageActual = true;
            $stopRequestedKey = "stream_stop:{$conversation->id}";
            $stopped = false;
            $messages = $messagesHistory;
            $maxSteps = 15;
            $maxTokens = (int) config('services.tokenomix.chat_max_tokens', 16384) ?: null;

            try {
                $send([
                    'event' => 'start',
                    'model' => $selectedModel,
                    'user_message_id' => $userMsgId,
                    'assistant_message_id' => $assistantMsg->id,
                    'stream_id' => $streamId,
                ]);

                for ($step = 1; $step <= $maxSteps; $step++) {
                    $turnText = '';
                    $reportedUsage = null;

                    $messages = $toolbox->compactHistory($messages);
                    $payload = [
                        'model' => $selectedModel,
                        'messages' => $messages,
                        'temperature' => 0.7,
                    ];
                    // Without an explicit limit some providers stop at ~4k output tokens, which cuts
                    // a tool call that writes whole files in half (arguments arrive empty).
                    if ($maxTokens) {
                        $payload['max_tokens'] = $maxTokens;
                    }
                    if ($toolsEnabled) {
                        $payload['tools'] = $toolbox->definitions();
                        $payload['tool_choice'] = 'auto';
                    }

                    $onChunk = function ($chunkText, $isDone, $usage) use (&$fullContent, &$turnText, &$reportedUsage, &$stopped, $stopRequestedKey, $send) {
                        if (Cache::has($stopRequestedKey)) {
                            $stopped = true;
                            $send(['event' => 'stopped', 'message' => 'Generasi dibatalkan oleh pengguna.']);
                            return false;
                        }
                        if ($usage) {
                            $reportedUsage = $usage;
                        }
                        if (!$isDone && $chunkText !== '') {
                            $turnText .= $chunkText;
                            $fullContent .= $chunkText;
                            $send(['content' => $chunkText]);
                        }
                        return true;
                    };

                    $stepStartContent = $fullContent;
                    $result = null;
                    for ($attempt = 1; $attempt <= self::MAX_UPSTREAM_ATTEMPTS; $attempt++) {
                        try {
                            $result = $this->client->streamChatCompletion($payload, $onChunk, $onTick);
                            break;
                        } catch (UpstreamRetryableException $e) {
                            if ($attempt >= self::MAX_UPSTREAM_ATTEMPTS || $stopped) {
                                throw $e;
                            }
                            // Drop the partial answer of this step and try the same step again
                            $fullContent = $stepStartContent;
                            $turnText = '';
                            $reportedUsage = null;
                            $base = (int) config('services.tokenomix.retry_base_seconds', 2);
                            $wait = min(30, max($e->retryAfterSeconds ?? 0, $base * 2 ** ($attempt - 1)));
                            $send([
                                'event' => 'retry',
                                'attempt' => $attempt + 1,
                                'max' => self::MAX_UPSTREAM_ATTEMPTS,
                                'wait' => $wait,
                                'message' => $e->getMessage(),
                                'content' => $fullContent,
                            ]);
                            for ($t = 0; $t < $wait; $t++) {
                                if (Cache::has($stopRequestedKey)) {
                                    $stopped = true;
                                    break;
                                }
                                sleep(1);
                                $heartbeat();
                            }
                            if ($stopped) {
                                break;
                            }
                        } catch (Exception $e) {
                            // Model rejects the requested output size: retry with the provider default
                            if ($maxTokens && $turnText === '' && preg_match('/max_tokens|max_completion_tokens|output tokens|maximum.*tokens/i', $e->getMessage())) {
                                $maxTokens = null;
                                unset($payload['max_tokens']);
                                $attempt--;
                                continue;
                            }
                            // Model/provider without function calling: retry this step without tools
                            if ($toolsEnabled && $turnText === '' && preg_match('/tool|function/i', $e->getMessage())) {
                                $toolsEnabled = false;
                                unset($payload['tools'], $payload['tool_choice']);
                                $attempt--;
                                continue;
                            }
                            throw $e;
                        }
                    }

                    if ($result === null) {
                        break; // stopped while waiting to retry
                    }

                    // Token accounting per model call (gateway formula: (prompt + completion) × multiplier)
                    $pTokens = $reportedUsage['prompt_tokens'] ?? $this->accounting->estimateTokenCount(json_encode($messages));
                    $cTokens = $reportedUsage['completion_tokens'] ?? $this->accounting->estimateTokenCount($turnText . json_encode($result['tool_calls'] ?? []));
                    $usageActual = $usageActual && $reportedUsage !== null;
                    $promptTotal += $pTokens;
                    $completionTotal += $cTokens;

                    $this->accounting->recordUsage(
                        user: $user,
                        model: $selectedModel,
                        idempotencyKey: $idempotencyKey . '_s' . $step,
                        promptTokens: $pTokens,
                        completionTokens: $cTokens,
                        dataSource: $reportedUsage ? 'actual' : 'estimated',
                        status: $stopped ? 'cancelled' : 'success',
                        projectId: $conversation->fresh()->project_id,
                        conversationId: (string) $conversation->id
                    );

                    if ($stopped || $result['aborted'] || empty($result['tool_calls'])) {
                        break;
                    }

                    // Run the tools the model asked for, then let it continue with the results
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $turnText !== '' ? $turnText : null,
                        'tool_calls' => $result['tool_calls'],
                    ];

                    foreach ($result['tool_calls'] as $call) {
                        $name = $call['function']['name'];
                        $args = json_decode($call['function']['arguments'] ?: '{}', true);
                        $badArgs = !is_array($args);
                        if ($badArgs) {
                            $args = [];
                        }

                        $label = $toolbox->describe($name, $args);
                        $send(['event' => 'tool', 'status' => 'running', 'id' => $call['id'], 'label' => $label]);

                        if (($result['finish_reason'] ?? null) === 'length') {
                            $toolResult = ['error' => 'Jawabanmu terpotong karena batas panjang output, jadi tool ini TIDAK dijalankan. Bagi pekerjaan: tulis 1-2 berkas per panggilan tool.'];
                        } elseif ($badArgs) {
                            $toolResult = ['error' => 'Argumen tool tidak valid atau terpotong (kemungkinan terlalu panjang). Kirim lebih sedikit berkas per panggilan, lalu coba lagi.'];
                        } elseif (Cache::has($stopRequestedKey)) {
                            $stopped = true;
                            $toolResult = ['error' => 'Dibatalkan oleh pengguna.'];
                        } else {
                            $toolResult = $toolbox->execute($name, $args, $user, $conversation);
                            $conversation->refresh();
                        }

                        $ok = !isset($toolResult['error']) || isset($toolResult['content']);
                        $link = $toolResult['commit_url'] ?? $toolResult['url'] ?? null;
                        $send([
                            'event' => 'tool',
                            'status' => $ok ? 'done' : 'error',
                            'id' => $call['id'],
                            'label' => $label,
                            'link' => $link,
                            'error' => $ok ? null : $toolResult['error'],
                            'project_id' => $toolResult['project_id'] ?? null,
                        ]);

                        $activity[] = ($ok ? '**[OK]** ' : '**[GAGAL]** ') . $label
                            . ($link ? " — [" . (isset($toolResult['commit_sha']) ? 'commit ' . substr($toolResult['commit_sha'], 0, 7) : 'buka') . "]({$link})" : '')
                            . ($ok ? '' : ': ' . mb_substr((string) $toolResult['error'], 0, 300));

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $call['id'],
                            'content' => $toolbox->encodeResult($toolResult),
                        ];
                    }

                    // Save progress so a dropped browser connection can pick up the work later
                    $assistantMsg->update(['content' => $this->activityBlock($activity) . $fullContent]);

                    if ($stopped) {
                        break;
                    }

                    if ($fullContent !== '' && !str_ends_with($fullContent, "\n")) {
                        $fullContent .= "\n\n";
                        $send(['content' => "\n\n"]);
                    }

                    if ($step === $maxSteps) {
                        $note = "\n\n_Batas langkah otomatis tercapai. Kirim \"lanjutkan\" untuk meneruskan._";
                        $fullContent .= $note;
                        $send(['content' => $note]);
                    }
                }

                $send('[DONE]');

                $assistantMsg->update([
                    'content' => $this->activityBlock($activity) . $fullContent,
                    'status' => $stopped ? 'cancelled' : 'completed',
                    'prompt_tokens' => $usageActual ? $promptTotal : null,
                    'completion_tokens' => $usageActual ? $completionTotal : null,
                ]);
            } catch (Exception $e) {
                $hint = $e instanceof UpstreamRetryableException
                    ? "\n\nGateway masih belum bisa dihubungi setelah " . self::MAX_UPSTREAM_ATTEMPTS . " kali percobaan. Aksi yang sudah berhasil di atas tetap tersimpan; kirim \"lanjutkan\" untuk meneruskan."
                    : '';
                $assistantMsg->update([
                    'content' => $this->activityBlock($activity) . $fullContent . "\n\n[Kesalahan Streaming]: " . $e->getMessage() . $hint,
                    'status' => 'failed',
                ]);

                $send(['error' => $e->getMessage()]);
            } finally {
                Cache::forget($stopRequestedKey);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Execute a chat "push ke <github link>" command and answer as an SSE stream,
     * so the chat UI renders it like any other assistant reply.
     */
    protected function streamPushCommand(Conversation $conversation, $user, string $content, array $command): StreamedResponse
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $content,
            'status' => 'completed',
        ]);

        if ($conversation->title === 'Percakapan Baru') {
            $conversation->update(['title' => mb_substr('Push ke ' . $command['repo'], 0, 40)]);
        }

        $reply = $this->runPushCommand($conversation, $user, $command);

        $assistantMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply,
            'status' => 'completed',
        ]);
        $conversation->touch();

        return response()->stream(function () use ($reply, $assistantMsg) {
            echo "data: " . json_encode(['event' => 'start', 'assistant_message_id' => $assistantMsg->id, 'github_push' => true]) . "\n\n";
            echo "data: " . json_encode(['content' => $reply]) . "\n\n";
            echo "data: [DONE]\n\n";
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function runPushCommand(Conversation $conversation, $user, array $command): string
    {
        $github = app(GitHubService::class);
        $commitMessage = $command['commit_message'] ?: null;

        try {
            if ($conversation->project_id) {
                $project = Project::where('id', $conversation->project_id)->where('user_id', $user->id)->firstOrFail();
                $result = $github->pushProjectCheckpoint(
                    $project,
                    $user,
                    $commitMessage ?: ('Push dari XZY: ' . $project->name),
                    $command['branch'] ?: null,
                    $command['repo']
                );
                $what = "proyek **{$project->name}**";
            } else {
                $contents = $conversation->messages()
                    ->where('role', 'assistant')
                    ->where('status', '!=', 'failed')
                    ->pluck('content');
                $files = CodeBlockExtractor::latestFiles($contents);

                if (empty($files)) {
                    return "Belum ada berkas untuk di-push ke **{$command['repo']}**.\n\n"
                        . "Percakapan ini belum terhubung ke proyek, dan belum ada berkas berlabel path di chat. "
                        . "Pilih proyek di header chat (agar seluruh berkas proyek yang di-push), atau minta AI menulis berkas dengan format "
                        . "```bahasa:path/berkas.ext lalu kirim lagi perintah push.";
                }

                $result = $github->pushFiles(
                    $user,
                    $command['repo'],
                    $command['branch'] ?: '',
                    $files,
                    $commitMessage ?: ('Update dari chat XZY: ' . mb_substr($conversation->title ?: 'percakapan', 0, 60)),
                    ['audit_type' => 'Conversation', 'audit_id' => (string) $conversation->id]
                );
                $what = count($result['files']) . ' berkas dari chat ini';
            }
        } catch (Exception $e) {
            return "**[GAGAL] Push ke GitHub gagal** ({$command['repo']})\n\n" . $e->getMessage();
        }

        $fileList = implode("\n", array_map(fn ($f) => "- `{$f}`", array_slice($result['files'], 0, 30)));
        if (count($result['files']) > 30) {
            $fileList .= "\n- … dan " . (count($result['files']) - 30) . ' berkas lain';
        }

        return "**[OK] Berhasil push {$what}** ke [{$result['repo']}]({$result['repo_url']}) branch `{$result['branch']}`"
            . ($result['branch_created'] ? ' (branch baru)' : '') . ".\n\n"
            . "Commit: [{$result['commit_sha']}]({$result['commit_url']})\n\n{$fileList}";
    }

    /**
     * Push files written in the chat straight to a GitHub repository branch.
     */
    public function pushToGitHub(Request $request, string $id, GitHubService $github): JsonResponse
    {
        $conversation = $this->getAuthorizedConversation($request, $id);
        $user = $request->user();

        $validated = $request->validate([
            'repo' => ['required', 'string', 'max:255'],
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

    protected function activityBlock(array $activity): string
    {
        return empty($activity) ? '' :
            "<details><summary>Aksi GitHub & proyek (" . count($activity) . ")</summary>\n\n- "
            . implode("\n- ", $activity) . "\n\n</details>\n\n";
    }

    /**
     * Facts the model needs to use its tools: GitHub connection and linked project.
     */
    protected function runtimeContext(Conversation $conversation, $user): string
    {
        $conn = $user->githubConnection;
        $lines = [];
        $lines[] = ($conn && $conn->status === 'connected')
            ? "GitHub: terhubung sebagai @{$conn->github_username}."
            : 'GitHub: BELUM terhubung. Jika pengguna meminta operasi GitHub, minta ia menghubungkan Personal Access Token di menu Pengaturan & GitHub.';

        if ($conversation->project_id) {
            $project = Project::where('id', $conversation->project_id)->where('user_id', $user->id)->first();
            if ($project) {
                $lines[] = "Proyek terhubung: \"{$project->name}\" ({$project->files()->count()} berkas)"
                    . ($project->github_repo ? ", target GitHub {$project->github_repo}:" . ($project->github_branch ?: 'main') : ', belum punya target GitHub') . '.';
            }
        } else {
            $lines[] = 'Percakapan ini belum terhubung ke proyek XZY.';
        }

        return implode("\n", $lines);
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
