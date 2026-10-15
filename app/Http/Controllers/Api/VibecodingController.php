<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRun;
use App\Services\Agent\VibecodingAgent;
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

        try {
            $run = $this->agent->executeRun(
                project: $project,
                user: $user,
                prompt: $validated['prompt'],
                model: $validated['model'] ?? null,
                autoApply: $autoApply
            );

            return response()->json([
                'message' => 'Siklus vibecoding berhasil dijalankan.',
                'run' => $run,
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
