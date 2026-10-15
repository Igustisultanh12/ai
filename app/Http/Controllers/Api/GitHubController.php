<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\GitHub\GitHubService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubController extends Controller
{
    protected GitHubService $github;

    public function __construct(GitHubService $github)
    {
        $this->github = $github;
    }

    /**
     * Get connection status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $conn = $user->githubConnection;

        return response()->json([
            'is_connected' => !empty($conn) && $conn->status === 'connected',
            'username' => $conn?->github_username,
            'scopes' => $conn?->scopes ?? [],
            'last_sync_at' => $conn?->last_sync_at,
        ]);
    }

    /**
     * Connect GitHub token.
     */
    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            $conn = $this->github->connectUser($request->user(), $validated['token']);
            return response()->json([
                'message' => 'Akun GitHub berhasil dihubungkan.',
                'username' => $conn->github_username,
                'scopes' => $conn->scopes,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Disconnect GitHub token.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $this->github->disconnectUser($request->user());
        return response()->json(['message' => 'Integrasi GitHub berhasil diputuskan.']);
    }

    /**
     * List user's GitHub repositories.
     */
    public function repos(Request $request): JsonResponse
    {
        try {
            $repos = $this->github->listRepositories($request->user());
            return response()->json(['repos' => $repos]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * List branches for repository.
     */
    public function branches(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'repo' => ['required', 'string'],
        ]);

        try {
            $branches = $this->github->listBranches($request->user(), $validated['repo']);
            return response()->json(['branches' => $branches]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Manually push current project checkpoint to selected GitHub repo and branch.
     */
    public function push(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $project = Project::where('id', $projectId)->where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:100'],
        ]);

        $commitMsg = $validated['message'] ?: ('Update proyek XZY: ' . now()->toDateTimeString());
        $branch = $validated['branch'] ?: ($project->github_branch ?: 'main');

        try {
            $result = $this->github->pushProjectCheckpoint($project, $user, $commitMsg, $branch);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
