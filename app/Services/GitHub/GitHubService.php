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
        $repoFullName = $project->github_repo;
        $branch = $targetBranch ?: ($project->github_branch ?: 'main');

        if (empty($repoFullName)) {
            throw new Exception("Repositori GitHub target belum diatur pada proyek ini.");
        }

        $allFiles = $project->files()->pluck('content', 'path')->toArray();

        $result = $this->pushFiles($user, $repoFullName, $branch, $allFiles, $commitMessage, [
            'audit_type' => 'Project',
            'audit_id' => (string) $project->id,
        ]);

        // Save commit SHA on active checkpoint
        if ($project->activeCheckpoint) {
            $project->activeCheckpoint->update(['github_commit_sha' => $result['commit_sha']]);
        }

        return $result;
    }

    /**
     * Commit a set of files (path => content) to a repository branch in one commit.
     * Missing branches are created from the repository's default branch.
     * Never force-pushes.
     *
     * @param array<string, string> $files
     */
    public function pushFiles(
        User $user,
        string $repoFullName,
        string $branch,
        array $files,
        string $commitMessage,
        array $context = []
    ): array {
        $token = $this->getUserToken($user);
        $repoFullName = trim($repoFullName);
        $branch = trim($branch) ?: 'main';

        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repoFullName)) {
            throw new Exception("Nama repositori tidak valid. Gunakan format pemilik/nama-repo.");
        }

        if (!$this->isValidBranchName($branch)) {
            throw new Exception("Nama branch tidak valid: {$branch}");
        }

        // Prevent pointing user content to XZY app's own source repository
        if (strtolower($repoFullName) === 'igustisultanh12/ai') {
            throw new Exception("Repositori sistem XZY tidak dapat digunakan sebagai target push. Harap pilih repositori GitHub pribadi Anda.");
        }

        // 1. Normalise paths and drop ignored files
        $filesToPush = [];
        $ignoredPatterns = ['vendor/', 'node_modules/', '.git/', '.DS_Store'];
        foreach ($files as $path => $content) {
            $clean = $this->sanitizePath((string) $path);
            if ($clean === null) {
                throw new Exception("Path berkas tidak valid: {$path}");
            }

            $skip = basename($clean) === '.env';
            foreach ($ignoredPatterns as $pattern) {
                if (str_starts_with($clean, $pattern) || str_contains($clean, '/' . $pattern) || basename($clean) === $pattern) {
                    $skip = true;
                    break;
                }
            }
            if (!$skip) {
                $filesToPush[$clean] = (string) $content;
            }
        }

        if (empty($filesToPush)) {
            throw new Exception("Tidak ada berkas yang dapat di-push.");
        }

        // 2. Pre-push secret scanning
        $scan = $this->scanForSecrets($filesToPush);
        if ($scan['has_secrets']) {
            throw new Exception("Push dibatalkan karena terdeteksi kredensial rahasia:\n" . implode("\n", $scan['violations']));
        }

        $api = "https://api.github.com/repos/{$repoFullName}";
        $http = fn () => Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'XZY-Platform',
        ])->timeout(30);

        // 3. Resolve parent commit: target branch, else the repo default branch
        $parentCommitSha = null;
        $baseTreeSha = null;
        $branchExists = false;

        $refRes = $http()->get("{$api}/git/ref/heads/" . $this->encodeRef($branch));
        if ($refRes->successful()) {
            $branchExists = true;
            $parentCommitSha = $refRes->json('object.sha');
        } elseif ($refRes->status() === 404) {
            $repoRes = $http()->get($api);
            if (!$repoRes->successful()) {
                throw new Exception("Repositori {$repoFullName} tidak ditemukan atau token tidak punya akses (HTTP {$repoRes->status()}).");
            }
            $defaultBranch = $repoRes->json('default_branch');
            if ($defaultBranch) {
                $baseRef = $http()->get("{$api}/git/ref/heads/" . $this->encodeRef($defaultBranch));
                if ($baseRef->successful()) {
                    $parentCommitSha = $baseRef->json('object.sha');
                }
            }
        } else {
            throw new Exception("Gagal membaca branch {$branch} (HTTP {$refRes->status()}): " . ($refRes->json('message') ?? $refRes->body()));
        }

        if ($parentCommitSha) {
            $commitRes = $http()->get("{$api}/git/commits/{$parentCommitSha}");
            if ($commitRes->successful()) {
                $baseTreeSha = $commitRes->json('tree.sha');
            }
        }

        // 4. Create blobs
        $treeItems = [];
        foreach ($filesToPush as $path => $content) {
            $blobRes = $http()->post("{$api}/git/blobs", [
                'content' => base64_encode($content),
                'encoding' => 'base64',
            ]);

            if (!$blobRes->successful()) {
                $hint = $blobRes->status() === 409 ? ' Repositori masih kosong: buat commit awal (mis. README) di GitHub terlebih dahulu.' : '';
                throw new Exception("Gagal mengunggah berkas {$path} ke GitHub (HTTP {$blobRes->status()}): " . ($blobRes->json('message') ?? $blobRes->body()) . $hint);
            }

            $treeItems[] = [
                'path' => $path,
                'mode' => '100644',
                'type' => 'blob',
                'sha' => $blobRes->json('sha'),
            ];
        }

        // 5. Create tree
        $treePayload = ['tree' => $treeItems];
        if ($baseTreeSha) {
            $treePayload['base_tree'] = $baseTreeSha;
        }

        $newTreeRes = $http()->post("{$api}/git/trees", $treePayload);
        if (!$newTreeRes->successful()) {
            throw new Exception("Gagal membuat git tree pada GitHub: " . ($newTreeRes->json('message') ?? $newTreeRes->body()));
        }

        // 6. Create commit
        $newCommitRes = $http()->post("{$api}/git/commits", [
            'message' => $commitMessage,
            'tree' => $newTreeRes->json('sha'),
            'parents' => $parentCommitSha ? [$parentCommitSha] : [],
        ]);
        if (!$newCommitRes->successful()) {
            throw new Exception("Gagal membuat git commit pada GitHub: " . ($newCommitRes->json('message') ?? $newCommitRes->body()));
        }

        $newCommitSha = $newCommitRes->json('sha');

        // 7. Move or create the branch ref (fast-forward only)
        if ($branchExists) {
            $updateRefRes = $http()->patch("{$api}/git/refs/heads/" . $this->encodeRef($branch), [
                'sha' => $newCommitSha,
                'force' => false, // STRICTLY no force push to prevent overwriting user work
            ]);
        } else {
            $updateRefRes = $http()->post("{$api}/git/refs", [
                'ref' => "refs/heads/{$branch}",
                'sha' => $newCommitSha,
            ]);
        }

        if (!$updateRefRes->successful()) {
            $errorBody = (string) ($updateRefRes->json('message') ?? $updateRefRes->body());
            if (stripos($errorBody, 'fast forward') !== false || stripos($errorBody, 'fast-forward') !== false || $updateRefRes->status() === 409) {
                throw new Exception("Konflik remote terdeteksi (non-fast-forward). Branch {$branch} berubah selama push. Coba push ulang.");
            }
            throw new Exception("Gagal memperbarui referensi branch GitHub: " . $errorBody);
        }

        $this->audit->log('github.push', $user, $context['audit_type'] ?? 'GitHub', $context['audit_id'] ?? $repoFullName, [
            'repo' => $repoFullName,
            'branch' => $branch,
            'commit_sha' => $newCommitSha,
            'files' => count($treeItems),
            'branch_created' => !$branchExists,
        ]);

        return [
            'success' => true,
            'commit_sha' => $newCommitSha,
            'commit_url' => "https://github.com/{$repoFullName}/commit/{$newCommitSha}",
            'repo' => $repoFullName,
            'branch' => $branch,
            'branch_created' => !$branchExists,
            'files' => array_keys($filesToPush),
            'message' => "Berhasil push " . count($treeItems) . " berkas ke {$repoFullName}:{$branch}",
        ];
    }

    /**
     * Normalise a repository-relative path; returns null when unsafe.
     */
    public function sanitizePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^\./#', '', $path);
        $path = preg_replace('#/+#', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path)) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || strtolower($segment) === '.git') {
                return null;
            }
        }

        if (strlen($path) > 400 || preg_match('/[\x00-\x1F]/', $path)) {
            return null;
        }

        return $path;
    }

    protected function isValidBranchName(string $branch): bool
    {
        return (bool) preg_match('#^(?!/)(?!.*//)(?!.*\.\.)(?!.*@\{)[A-Za-z0-9._/\-]{1,100}(?<!/)(?<!\.lock)(?<!\.)$#', $branch);
    }

    protected function encodeRef(string $ref): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $ref)));
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
