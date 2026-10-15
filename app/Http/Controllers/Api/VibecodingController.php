<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRun;
use App\Services\Agent\VibecodingAgent;
use App\Services\GitHub\GitHubService;
use App\Services\GitHub\PushCommandParser;
use App\Services\Sandbox\SandboxRunner;
use App\Services\Sandbox\WorkspaceManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VibecodingController extends Controller
{
    protected VibecodingAgent $agent;
    protected WorkspaceManager $workspace;
    protected SandboxRunner $sandbox;

    public function __construct(VibecodingAgent $agent, WorkspaceManager $workspace, SandboxRunner $sandbox)
    {
        $this->agent = $agent;
        $this->workspace = $workspace;
        $this->sandbox = $sandbox;
    }

    /**
     * Trigger a vibecoding agent run.
     */
    public function run(Request $request, string $projectId): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $projectId);
        $user = $request->user();

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:3'],
            'model' => ['nullable', 'string'],
            'auto_apply' => ['nullable', 'boolean'],
        ]);

        $autoApply = $validated['auto_apply'] ?? true;

        // "push ke https://github.com/user/repo" -> push this project to that repo
        $pushCommand = app(PushCommandParser::class)->parse($validated['prompt']);

        if ($pushCommand && $pushCommand['push_only']) {
            return $this->pushProjectFromPrompt($project, $user, $pushCommand);
        }

        try {
            $run = $this->agent->executeRun(
                project: $project,
                user: $user,
                prompt: $validated['prompt'],
                model: $validated['model'] ?? null,
                autoApply: $autoApply
            );

            $push = null;
            if ($pushCommand) {
                // Coding task + push in one prompt: push only once the changes are applied and valid
                if ($run->status === 'completed' && $autoApply) {
                    $push = $this->tryPush($project->fresh(), $user, $pushCommand);
                    $run->update([
                        'build_log' => trim(($run->build_log ?? '') . "\n" . ($push['success']
                            ? "[OK] Push ke GitHub: {$push['commit_url']}"
                            : "[PERINGATAN] Push ke GitHub gagal: {$push['message']}")),
                    ]);
                } else {
                    $push = [
                        'success' => false,
                        'message' => $autoApply
                            ? 'Push ke GitHub dilewati karena run vibecoding belum lulus verifikasi.'
                            : 'Push ke GitHub dilewati: terapkan perubahan dulu, lalu kirim lagi perintah push.',
                    ];
                }
            }

            return response()->json([
                'message' => 'Siklus vibecoding berhasil dijalankan.',
                'run' => $run->fresh(),
                'push' => $push,
                'project' => $project->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Eksekusi vibecoding gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List runs for a project.
     */
    public function runs(Request $request, string $projectId): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $projectId);

        $runs = ProjectRun::where('project_id', $project->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($runs);
    }

    /**
     * Get details of a specific run.
     */
    public function getRun(Request $request, string $projectId, string $runId): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $projectId);
        $run = ProjectRun::where('project_id', $project->id)->where('id', $runId)->firstOrFail();

        return response()->json(['run' => $run]);
    }

    /**
     * Manually apply proposed patches from a run (used in review mode).
     */
    public function applyProposedPatches(Request $request, string $projectId, string $runId): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $projectId);
        $user = $request->user();
        $run = ProjectRun::where('project_id', $project->id)->where('id', $runId)->firstOrFail();

        $patches = $run->proposed_patches;
        if (empty($patches) || !is_array($patches)) {
            return response()->json(['message' => 'Tidak ada usulan perubahan pada run ini.'], 400);
        }

        // Create atomic pre-apply checkpoint
        $this->workspace->createCheckpoint($project, $user->id, "Sebelum apply manual run {$runId}");

        foreach ($patches as $patch) {
            if (!empty($patch['path']) && isset($patch['new_content'])) {
                $this->workspace->putFile($project, $patch['path'], $patch['new_content']);
            }
        }

        // Run sandbox check
        $check = $this->sandbox->verifyProject($project);

        $status = $check['success'] ? 'passed' : 'failed';
        $this->workspace->createCheckpoint(
            $project,
            $user->id,
            "Penerapan manual run {$runId}",
            $status,
            $status
        );

        return response()->json([
            'message' => 'Semua perubahan berhasil diterapkan.',
            'build_status' => $status,
            'build_log' => $check['log'],
            'project' => $project->fresh(['files', 'activeCheckpoint']),
        ]);
    }

    /**
     * Handle a prompt that only asks to push the project to a GitHub repo link.
     */
    protected function pushProjectFromPrompt(Project $project, $user, array $command): JsonResponse
    {
        $push = $this->tryPush($project, $user, $command);

        $run = ProjectRun::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'type' => 'github_push',
            'status' => $push['success'] ? 'completed' : 'failed',
            'plan' => $push['success']
                ? "Proyek \"{$project->name}\" berhasil di-push ke https://github.com/{$push['repo']} (branch {$push['branch']}).\nCommit: {$push['commit_url']}\n\nRepo ini sekarang tersimpan sebagai target GitHub proyek."
                : "Push ke GitHub gagal: {$push['message']}",
            'proposed_patches' => [],
            'build_log' => $push['success']
                ? "[OK] " . $push['message'] . "\n" . implode("\n", array_map(fn ($f) => " - {$f}", $push['files']))
                : "[GAGAL] " . $push['message'],
            'iterations_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => $push['message'],
            'run' => $run,
            'push' => $push,
            'project' => $project->fresh(),
        ], $push['success'] ? 200 : 422);
    }

    protected function tryPush(Project $project, $user, array $command): array
    {
        try {
            return app(GitHubService::class)->pushProjectCheckpoint(
                $project,
                $user,
                $command['commit_message'] ?: ('Push dari XZY: ' . $project->name),
                $command['branch'] ?: null,
                $command['repo']
            );
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function getAuthorizedProject(Request $request, string $id): Project
    {
        $user = $request->user();
        $project = Project::where('id', $id)->firstOrFail();

        if ($project->user_id !== $user->id) {
            abort(403, 'Akses proyek ditolak.');
        }

        return $project;
    }
}
