<?php

namespace App\Services\Agent;

use App\Models\AgentConfig;
use App\Models\ModelMultiplier;
use App\Models\Project;
use App\Models\ProjectRun;
use App\Models\User;
use App\Services\GitHub\GitHubService;
use App\Services\Sandbox\SandboxRunner;
use App\Services\Sandbox\WorkspaceManager;
use App\Services\Usage\TokenAccountingService;
use Exception;
use Illuminate\Support\Str;

class VibecodingAgent
{
    protected TokenomixClient $client;
    protected ToolRegistry $tools;
    protected ContextManager $contextManager;
    protected WorkspaceManager $workspace;
    protected SandboxRunner $sandbox;
    protected TokenAccountingService $accounting;
    protected GitHubService $github;

    public function __construct(
        TokenomixClient $client,
        ToolRegistry $tools,
        ContextManager $contextManager,
        WorkspaceManager $workspace,
        SandboxRunner $sandbox,
        TokenAccountingService $accounting,
        GitHubService $github
    ) {
        $this->client = $client;
        $this->tools = $tools;
        $this->contextManager = $contextManager;
        $this->workspace = $workspace;
        $this->sandbox = $sandbox;
        $this->accounting = $accounting;
        $this->github = $github;
    }

    /**
     * Run the full vibecoding lifecycle.
     */
    public function executeRun(
        Project $project,
        User $user,
        string $prompt,
        ?string $model = null,
        bool $autoApply = true,
        int $maxIterations = 3
    ): ProjectRun {
        $activeConfig = AgentConfig::where('is_active', true)->first() ?: AgentConfig::first();
        $selectedModel = $model ?: ($activeConfig?->default_model ?: 'glm-5.1');

        // 1. Quota check
        if (!$this->accounting->checkQuota($user, 2000, $selectedModel)) {
            throw new Exception("Batas kuota token harian atau bulanan Anda telah tercapai. Harap hubungi administrator.");
        }

        // 2. Initialize Run record
        $run = ProjectRun::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'type' => 'vibecoding',
            'status' => 'running',
            'plan' => 'Menganalisis permintaan dan memindai ruang kerja proyek...',
            'started_at' => now(),
        ]);

        $runLog = [];
        $runLog[] = "[Vibecoding Run] Memulai eksekusi model: {$selectedModel}";

        // 3. Build project context
        $context = $this->contextManager->buildProjectContext($project, $prompt, 6000);

        // 4. Check if model supports tools natively
        $modelInfo = ModelMultiplier::where('model_id', $selectedModel)->first();
        $supportsNativeTools = $modelInfo ? $modelInfo->supports_tools : true;

        $systemPrompt = $this->buildSystemPrompt($supportsNativeTools);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Konteks Proyek saat ini:\n{$context}\n\nPermintaan Pengguna:\n{$prompt}"],
        ];

        $patches = [];
        $iteration = 0;
        $verificationPassed = false;
        $planText = '';

        while ($iteration < $maxIterations && !$verificationPassed) {
            $iteration++;
            $runLog[] = "\n--- Iterasi {$iteration}/{$maxIterations} ---";

            $idempotencyKey = 'run_' . $run->id . '_iter_' . $iteration . '_' . Str::random(8);

            $payload = [
                'model' => $selectedModel,
                'messages' => $messages,
                'temperature' => 0.5,
                'max_tokens' => $activeConfig?->max_output_tokens ?: 4096,
            ];

            if ($supportsNativeTools) {
                $payload['tools'] = $this->tools->getFunctionDefinitions();
            }

            try {
                $response = $this->client->chatCompletion($payload);
            } catch (Exception $e) {
                $runLog[] = "Gagal memanggil TokenomixAI: " . $e->getMessage();
                $run->update([
                    'status' => 'failed',
                    'build_log' => implode("\n", $runLog),
                    'completed_at' => now(),
                ]);
                throw $e;
            }

            // Record token usage
            $usage = $response['usage'] ?? [];
            $promptTokens = $usage['prompt_tokens'] ?? null;
            $completionTokens = $usage['completion_tokens'] ?? null;
            $dataSource = ($promptTokens !== null && $completionTokens !== null) ? 'actual' : 'estimated';

            if ($dataSource === 'estimated') {
                $promptTokens = $this->accounting->estimateTokenCount(json_encode($messages));
                $completionTokens = $this->accounting->estimateTokenCount(json_encode($response));
            }

            $this->accounting->recordUsage(
                user: $user,
                model: $selectedModel,
                idempotencyKey: $idempotencyKey,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                dataSource: $dataSource,
                status: 'success',
                projectId: $project->id,
                runId: (string) $run->id,
                latencyMs: $response['_latency_ms'] ?? null,
                attemptCount: $response['_attempt'] ?? 1
            );

            $choice = $response['choices'][0]['message'] ?? [];
            $replyContent = $choice['content'] ?? '';
            $toolCalls = $choice['tool_calls'] ?? [];

            // Extract plan from text
            if (!empty($replyContent)) {
                $planText .= "\n" . $replyContent;
            }

            // Extract patches from tool calls OR structured output tags
            $extractedPatches = $this->extractPatches($replyContent, $toolCalls);

            if (empty($extractedPatches)) {
                $runLog[] = "Model tidak mengusulkan perubahan berkas baru.";
                break;
            }

            // Merge proposed patches
            foreach ($extractedPatches as $p) {
                $patches[$p['path']] = $p;
            }

            $runLog[] = "Terdeteksi usulan perubahan pada " . count($extractedPatches) . " berkas:";
            foreach ($extractedPatches as $p) {
                $runLog[] = " - {$p['path']}";
            }

            if ($autoApply) {
                // 5. Create atomic pre-apply checkpoint
                $checkpoint = $this->workspace->createCheckpoint(
                    $project,
                    $user->id,
                    "Sebelum iterasi {$iteration}: " . substr($prompt, 0, 40)
                );

                // 6. Apply patches safely
                foreach ($extractedPatches as $p) {
                    $this->workspace->putFile($project, $p['path'], $p['content']);
                }

                // 7. Verify inside sandbox
                $checkResult = $this->sandbox->verifyProject($project);
                $runLog[] = $checkResult['log'];

                if ($checkResult['success']) {
                    $verificationPassed = true;
                    $runLog[] = "✓ Kode berhasil divalidasi tanpa error.";
                    
                    // Create post-apply checkpoint
                    $this->workspace->createCheckpoint(
                        $project,
                        $user->id,
                        "Vibecoding selesai: " . substr($prompt, 0, 50),
                        'passed',
                        'passed'
                    );

                    // Auto-push to GitHub if enabled
                    if ($project->auto_push_github && !empty($project->github_repo)) {
                        $this->pushToGithubSafe($project, $user, $runLog);
                    }
                } else {
                    $runLog[] = "✗ Verifikasi kode gagal. Memulai perbaikan otomatis...";
                    $messages[] = ['role' => 'assistant', 'content' => $replyContent];
                    $messages[] = [
                        'role' => 'user',
                        'content' => "Hasil build/pengujian mengalami error berikut:\n" . implode("\n", $checkResult['errors']) . "\nHarap perbaiki berkas yang bersangkutan agar dapat lulus verifikasi.",
                    ];
                }
            } else {
                // Manual review mode: do not apply immediately
                $runLog[] = "[Mode Tinjau] Perubahan disimpan untuk ditinjau oleh pengguna sebelum diterapkan.";
                $verificationPassed = true;
            }
        }

        // Build diff list
        $diffs = $this->generateDiffs($project, $patches);

        $finalStatus = $verificationPassed ? 'completed' : 'failed';
        $run->update([
            'status' => $finalStatus,
            'plan' => trim($planText),
            'proposed_patches' => array_values($diffs),
            'build_log' => implode("\n", $runLog),
            'iterations_count' => $iteration,
            'completed_at' => now(),
        ]);

        return $run;
    }

    /**
     * Parse file patches from native tool calls or structured XML/Markdown blocks.
     */
    protected function extractPatches(string $content, array $toolCalls): array
    {
        $patches = [];

        // 1. From native tool calls
        foreach ($toolCalls as $call) {
            $func = $call['function'] ?? [];
            if (($func['name'] ?? '') === 'apply_patch') {
                $args = json_decode($func['arguments'] ?? '{}', true);
                if (!empty($args['path']) && isset($args['content'])) {
                    $patches[] = [
                        'path' => trim($args['path']),
                        'content' => $args['content'],
                    ];
                }
            }
        }

        // 2. From structured XML tags: <patch path="...">...</patch>
        if (preg_match_all('/<patch\s+path=["\']([^"\']+)["\']>(.*?)<\/patch>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $patches[] = [
                    'path' => trim($m[1]),
                    'content' => trim($m[2]),
                ];
            }
        }

        // 3. From markdown code blocks with file path header: ```file:path/to/file ... ```
        if (preg_match_all('/```(?:file:)?([a-zA-Z0-9_\-\.\/]+)\n(.*?)```/s', $content, $codeMatches, PREG_SET_ORDER)) {
            foreach ($codeMatches as $cm) {
                $path = trim($cm[1]);
                if (str_contains($path, '.') && !in_array($path, ['html', 'javascript', 'vue', 'json', 'php', 'bash', 'css', 'sql'])) {
                    $patches[] = [
                        'path' => $path,
                        'content' => $cm[2],
                    ];
                }
            }
        }

        return $patches;
    }

    /**
     * Compute clean diffs comparing original file content with proposed content.
     */
    protected function generateDiffs(Project $project, array $patches): array
    {
        $diffs = [];
        $existingFiles = $project->files()->pluck('content', 'path')->toArray();

        foreach ($patches as $patch) {
            $path = $patch['path'];
            $newContent = $patch['content'];
            $oldContent = $existingFiles[$path] ?? '';

            $diffs[] = [
                'path' => $path,
                'is_new' => !isset($existingFiles[$path]),
                'old_content' => $oldContent,
                'new_content' => $newContent,
            ];
        }

        return $diffs;
    }

    /**
     * Safely auto-push to GitHub avoiding secret leakage or build breakages.
     */
    protected function pushToGithubSafe(Project $project, User $user, array &$runLog): void
    {
        try {
            $res = $this->github->pushProjectCheckpoint($project, $user, "Vibecoding auto-push: " . now()->toIso8601String());
            if ($res['success']) {
                $runLog[] = "✓ Berhasil auto-push ke GitHub: {$res['commit_url']}";
            } else {
                $runLog[] = "⚠ Auto-push GitHub dilewati: {$res['message']}";
            }
        } catch (Exception $e) {
            $runLog[] = "⚠ Gagal auto-push ke GitHub: " . $e->getMessage();
        }
    }

    /**
     * Build strict, high-performance system prompt for the coding agent.
     */
    protected function buildSystemPrompt(bool $supportsTools): string
    {
        $prompt = "Anda adalah AI Vibecoding Engineer Senior pada platform XZY.\n" .
                  "Tugas Anda: Memahami kebutuhan pengguna dan menulis kode yang bersih, aman, bebas bug, serta siap dijalankan.\n\n" .
                  "ATURAN KERJA:\n" .
                  "1. Buat kode lengkap tanpa TODO atau potongan yang belum selesai.\n" .
                  "2. Gunakan komponen modern Vue 3 (<script setup>), Tailwind CSS, dan Laravel.\n" .
                  "3. Pastikan sintaks PHP, JavaScript, dan JSON 100% valid tanpa kesalahan parser.\n";

        if ($supportsTools) {
            $prompt .= "4. Gunakan tool 'apply_patch' untuk setiap berkas yang dibuat atau dimodifikasi.\n";
        } else {
            $prompt .= "4. Karena tool native tidak tersedia pada model ini, gunakan format tag berikut untuk mengusulkan perubahan berkas:\n" .
                       "<patch path=\"path/ke/berkas.ext\">\n...isi berkas lengkap...\n</patch>\n";
        }

        return $prompt;
    }
}
