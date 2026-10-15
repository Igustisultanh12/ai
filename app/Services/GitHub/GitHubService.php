<?php

namespace App\Services\GitHub;

use App\Models\GitHubConnection;
use App\Models\Project;
use App\Models\ProjectCheckpoint;
use App\Models\User;
use App\Services\Auth\AuditService;
use Exception;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    protected AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Connect or update user's GitHub credential securely.
     */
    public function connectUser(User $user, string $token): GitHubConnection
    {
        // Verify token with GitHub API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'XZY-Platform',
        ])->get('https://api.github.com/user');

        if (!$response->successful()) {
            throw new Exception("Kredensial GitHub tidak valid. Harap periksa token atau izin yang diberikan.");
        }

        $githubData = $response->json();
        $scopesHeader = $response->header('X-OAuth-Scopes') ?: '';
        $scopes = array_filter(array_map('trim', explode(',', $scopesHeader)));

        $connection = GitHubConnection::updateOrCreate(
            ['user_id' => $user->id],
            [
                'github_username' => $githubData['login'] ?? 'unknown',
                'token_encrypted' => $token,
                'scopes' => $scopes,
                'status' => 'connected',
                'last_sync_at' => now(),
            ]
        );

        $this->audit->log('github.connected', $user, 'GitHubConnection', (string) $connection->id, [
            'github_username' => $githubData['login'] ?? null,
            'scopes' => $scopes,
        ]);

        return $connection;
    }

    /**
     * Disconnect and revoke user's GitHub integration.
     */
    public function disconnectUser(User $user): bool
    {
        $connection = $user->githubConnection;
        if ($connection) {
            $this->audit->log('github.disconnected', $user, 'GitHubConnection', (string) $connection->id);
            $connection->delete();
        }
        return true;
    }

    /**
     * List user's accessible repositories.
     */
    public function listRepositories(User $user): array
    {
        $token = $this->getUserToken($user);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'XZY-Platform',
        ])->get('https://api.github.com/user/repos', [
            'per_page' => 100,
            'sort' => 'updated',
        ]);

        if (!$response->successful()) {
            throw new Exception("Gagal mengambil repositori GitHub: " . $response->body());
        }

        return array_map(function ($repo) {
            return [
                'id' => $repo['id'],
                'name' => $repo['name'],
                'full_name' => $repo['full_name'],
                'private' => $repo['private'],
                'default_branch' => $repo['default_branch'],
                'html_url' => $repo['html_url'],
            ];
        }, $response->json());
    }

    /**
     * List branches for a specific repository.
     */
    public function listBranches(User $user, string $repoFullName): array
    {
        $token = $this->getUserToken($user);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'XZY-Platform',
        ])->get("https://api.github.com/repos/{$repoFullName}/branches");

        if (!$response->successful()) {
            throw new Exception("Gagal mengambil daftar branch GitHub: " . $response->body());
        }

        return array_map(fn ($b) => $b['name'], $response->json());
    }

    /**
     * Scan files for credentials, private keys, or secret tokens before pushing.
     */
    public function scanForSecrets(array $files): array
    {
        $patterns = [
            'AWS Access Key' => '/AKIA[0-9A-Z]{16}/',
            'GitHub Token' => '/gh[pousr]_[0-9a-zA-Z]{36}/',
            'Tokenomix/OpenAI Key' => '/sk-[a-zA-Z0-9_\-]{20,}/',
            'Private Key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
            'Database Password Assignment' => '/DB_PASSWORD\s*=\s*[\'"][^\'"]+[\'"]/',
        ];

        $violations = [];

        foreach ($files as $path => $content) {
            // Block .env file directly
            if (basename($path) === '.env' || str_starts_with(basename($path), '.env.')) {
                if (basename($path) !== '.env.example') {
                    $violations[] = "Berkas rahasia dilarang di-push: {$path}";
                }
            }

            if (empty($content) || !is_string($content)) {
                continue;
            }

            foreach ($patterns as $type => $regex) {
                if (preg_match($regex, $content)) {
                    $violations[] = "Potensi kebocoran {$type} terdeteksi pada berkas: {$path}";
                }
            }
        }

        return [
            'has_secrets' => !empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Push project files to GitHub repository atomically via GitHub Git Data API.
     */
    public function pushProjectCheckpoint(
        Project $project,
        User $user,
        string $commitMessage,
        ?string $targetBranch = null
    ): array {
        $token = $this->getUserToken($user);
        $repoFullName = $project->github_repo;
        $branch = $targetBranch ?: ($project->github_branch ?: 'main');

        if (empty($repoFullName)) {
            throw new Exception("Repositori GitHub target belum diatur pada proyek ini.");
        }

        // Prevent pointing user vibecoding project to XZY app's own source repository
        if (strtolower($repoFullName) === 'igustisultanh12/ai') {
            throw new Exception("Repositori sistem XZY tidak dapat digunakan sebagai target proyek pengguna. Harap pilih repositori GitHub pribadi Anda.");
        }

        // 1. Collect files excluding ignored paths
        $allFiles = $project->files()->pluck('content', 'path')->toArray();
        $filesToPush = [];
        $ignoredPatterns = ['/vendor/', '/node_modules/', '/.git/', '.DS_Store', '.env'];

        foreach ($allFiles as $path => $content) {
            $skip = false;
            foreach ($ignoredPatterns as $pattern) {
                if (str_contains($path, $pattern) || basename($path) === $pattern) {
                    $skip = true;
                    break;
                }
            }
            if (!$skip) {
                $filesToPush[$path] = $content;
            }
        }

        // 2. Pre-push secret scanning
        $scan = $this->scanForSecrets($filesToPush);
        if ($scan['has_secrets']) {
            throw new Exception("Push dibatalkan karena terdeteksi kredensial rahasia:\n" . implode("\n", $scan['violations']));
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'XZY-Platform',
        ];

        // 3. Get reference for branch
        $refRes = Http::withHeaders($headers)->get("https://api.github.com/repos/{$repoFullName}/git/ref/heads/{$branch}");
        
        $parentCommitSha = null;
        $baseTreeSha = null;

        if ($refRes->successful()) {
            $parentCommitSha = $refRes->json()['object']['sha'] ?? null;
            if ($parentCommitSha) {
                $commitRes = Http::withHeaders($headers)->get("https://api.github.com/repos/{$repoFullName}/git/commits/{$parentCommitSha}");
                if ($commitRes->successful()) {
                    $baseTreeSha = $commitRes->json()['tree']['sha'] ?? null;
                }
            }
        }

        // 4. Create blobs and tree
        $treeItems = [];
        foreach ($filesToPush as $path => $content) {
            $blobRes = Http::withHeaders($headers)->post("https://api.github.com/repos/{$repoFullName}/git/blobs", [
                'content' => $content,
                'encoding' => 'utf-8',
            ]);

            if ($blobRes->successful()) {
                $treeItems[] = [
                    'path' => $path,
                    'mode' => '100644',
                    'type' => 'blob',
                    'sha' => $blobRes->json()['sha'],
                ];
            }
        }

        // 5. Post tree
        $treePayload = ['tree' => $treeItems];
        if ($baseTreeSha) {
            $treePayload['base_tree'] = $baseTreeSha;
        }

        $newTreeRes = Http::withHeaders($headers)->post("https://api.github.com/repos/{$repoFullName}/git/trees", $treePayload);
        if (!$newTreeRes->successful()) {
            throw new Exception("Gagal membuat git tree pada GitHub: " . $newTreeRes->body());
        }

        $newTreeSha = $newTreeRes->json()['sha'];

        // 6. Create commit
        $commitPayload = [
            'message' => $commitMessage,
            'tree' => $newTreeSha,
            'parents' => $parentCommitSha ? [$parentCommitSha] : [],
        ];

        $newCommitRes = Http::withHeaders($headers)->post("https://api.github.com/repos/{$repoFullName}/git/commits", $commitPayload);
        if (!$newCommitRes->successful()) {
            throw new Exception("Gagal membuat git commit pada GitHub: " . $newCommitRes->body());
        }

        $newCommitSha = $newCommitRes->json()['sha'];

        // 7. Update reference (detect non-fast-forward)
        if ($parentCommitSha) {
            $updateRefRes = Http::withHeaders($headers)->patch("https://api.github.com/repos/{$repoFullName}/git/refs/heads/{$branch}", [
                'sha' => $newCommitSha,
                'force' => false, // STRICTLY no force push to prevent overwriting user work
            ]);
        } else {
            // Create branch ref
            $updateRefRes = Http::withHeaders($headers)->post("https://api.github.com/repos/{$repoFullName}/git/refs", [
                'ref' => "refs/heads/{$branch}",
                'sha' => $newCommitSha,
            ]);
        }

        if (!$updateRefRes->successful()) {
            $errorBody = $updateRefRes->body();
            if (str_contains($errorBody, 'not a fast-forward') || str_contains($errorBody, 'conflict')) {
                throw new Exception("Konflik remote terdeteksi (non-fast-forward). Branch remote telah memiliki perubahan baru. Harap buat branch baru atau perbarui proyek sebelum melakukan push.");
            }
            throw new Exception("Gagal memperbarui referensi branch GitHub: " . $errorBody);
        }

        $commitUrl = "https://github.com/{$repoFullName}/commit/{$newCommitSha}";

        // Save commit SHA on active checkpoint
        if ($project->activeCheckpoint) {
            $project->activeCheckpoint->update(['github_commit_sha' => $newCommitSha]);
        }

        $this->audit->log('github.push', $user, 'Project', (string) $project->id, [
            'repo' => $repoFullName,
            'branch' => $branch,
            'commit_sha' => $newCommitSha,
        ]);

        return [
            'success' => true,
            'commit_sha' => $newCommitSha,
            'commit_url' => $commitUrl,
            'branch' => $branch,
            'message' => "Berhasil melakukan push ke {$repoFullName}:{$branch}",
        ];
    }

    protected function getUserToken(User $user): string
    {
        $connection = $user->githubConnection;
        if (!$connection || empty($connection->token_encrypted)) {
            throw new Exception("Akun Anda belum terhubung ke GitHub. Hubungkan GitHub di menu Pengaturan Akun.");
        }
        return $connection->token_encrypted;
    }
}
