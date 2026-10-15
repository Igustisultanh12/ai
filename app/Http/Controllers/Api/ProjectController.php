<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCheckpoint;
use App\Services\Auth\AuditService;
use App\Services\Sandbox\WorkspaceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    protected WorkspaceManager $workspaceManager;
    protected AuditService $auditService;

    public function __construct(WorkspaceManager $workspaceManager, AuditService $auditService)
    {
        $this->workspaceManager = $workspaceManager;
        $this->auditService = $auditService;
    }

    /**
     * List projects owned by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Project::where('user_id', $user->id);

        if ($request->has('search') && !empty($request->search)) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('description', 'like', $s);
            });
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $projects = $query->with('activeCheckpoint')
            ->withCount('files')
            ->orderBy('updated_at', 'desc')
            ->paginate(12);

        return response()->json($projects);
    }

    /**
     * Create a new project with Laravel + Vue 3 starter template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'template' => ['nullable', 'string', 'in:laravel-vue3'],
        ]);

        $user = $request->user();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
            'description' => $validated['description'] ?? null,
            'template' => $validated['template'] ?? 'laravel-vue3',
            'visibility' => 'private',
            'status' => 'active',
            'github_branch' => 'main',
            'auto_push_github' => false,
        ]);

        // Initialize starter files
        $this->workspaceManager->initializeStarterTemplate($project);

        $this->auditService->log('project.created', $user, 'Project', (string) $project->id, [
            'name' => $project->name,
            'template' => $project->template,
        ]);

        return response()->json([
            'message' => 'Proyek berhasil dibuat dengan template awal.',
            'project' => $project->fresh(['files', 'activeCheckpoint']),
        ], 201);
    }

    /**
     * Show a project with ownership verification.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);

        return response()->json([
            'project' => $project->load(['activeCheckpoint', 'files']),
        ]);
    }

    /**
     * Update project settings.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'required', 'in:active,archived'],
            'github_repo' => ['nullable', 'string', 'max:255'],
            'github_branch' => ['nullable', 'string', 'max:100'],
            'auto_push_github' => ['sometimes', 'boolean'],
        ]);

        $project->update($validated);

        return response()->json([
            'message' => 'Pengaturan proyek berhasil diperbarui.',
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Delete project and wipe workspace.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);
        $user = $request->user();

        $this->auditService->log('project.deleted', $user, 'Project', (string) $project->id, [
            'name' => $project->name,
        ]);

        $workspaceDir = storage_path("app/workspaces/{$project->id}");
        if (is_dir($workspaceDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($workspaceDir);
        }

        $project->delete();

        return response()->json(['message' => 'Proyek berhasil dihapus.']);
    }

    /**
     * Get workspace file tree.
     */
    public function files(Request $request, string $id): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);
        $files = $this->workspaceManager->listFiles($project);

        return response()->json(['files' => $files]);
    }

    /**
     * Get content of a specific file.
     */
    public function getFile(Request $request, string $id): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);
        $path = $request->query('path');

        if (!$path) {
            return response()->json(['message' => 'Parameter path diperlukan.'], 400);
        }

        try {
            $content = $this->workspaceManager->getFile($project, $path);
            return response()->json([
                'path' => $path,
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * Update or create a file from the editor.
     */
    public function putFile(Request $request, string $id): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);

        $validated = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['present'], // can be empty string
        ]);

        try {
            $file = $this->workspaceManager->putFile($project, $validated['path'], $validated['content']);
            return response()->json([
                'message' => 'Berkas berhasil disimpan.',
                'file' => $file,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a file.
     */
    public function deleteFile(Request $request, string $id): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);
        $path = $request->query('path') ?: $request->input('path');

        if (!$path) {
            return response()->json(['message' => 'Parameter path diperlukan.'], 400);
        }

        try {
            $this->workspaceManager->deleteFile($project, $path);
            return response()->json(['message' => 'Berkas berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * List checkpoints for version history.
     */
    public function checkpoints(Request $request, string $id): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);

        $checkpoints = ProjectCheckpoint::where('project_id', $project->id)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['checkpoints' => $checkpoints]);
    }

    /**
     * Restore workspace from a checkpoint.
     */
    public function restoreCheckpoint(Request $request, string $id, string $checkpointId): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $id);
        $checkpoint = ProjectCheckpoint::where('project_id', $project->id)
            ->where('id', $checkpointId)
            ->firstOrFail();

        $this->workspaceManager->restoreCheckpoint($checkpoint);

        $this->auditService->log('project.checkpoint_restored', $request->user(), 'ProjectCheckpoint', (string) $checkpoint->id, [
            'project_id' => $project->id,
            'commit_message' => $checkpoint->commit_message,
        ]);

        return response()->json([
            'message' => "Proyek berhasil dipulihkan ke versi checkpoint: {$checkpoint->commit_message}",
            'project' => $project->fresh(['files', 'activeCheckpoint']),
        ]);
    }

    protected function getAuthorizedProject(Request $request, string $id): Project
    {
        $user = $request->user();
        $project = Project::where('id', $id)->firstOrFail();

        // Server-side ownership check
        if ($project->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses ke proyek ini.');
        }

        return $project;
    }
}
