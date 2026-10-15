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
        ?string $targetBranch = null,
        ?string $targetRepo = null
    ): array {
        $repoFullName = $this->normalizeRepo((string) ($targetRepo ?: $project->github_repo));
        $sameRepo = $repoFullName !== null && strcasecmp($repoFullName, (string) $project->github_repo) === 0;
        // A different (new) repo starts from its own default branch unless one was named
        $branch = $targetBranch ?: ($sameRepo ? ($project->github_branch ?: 'main') : '');

        if (empty($repoFullName)) {
            throw new Exception("Repositori GitHub target belum diatur pada proyek ini. Kirim link repo, misalnya: push ke https://github.com/username/nama-repo");
        }

        $allFiles = $project->files()->pluck('content', 'path')->toArray();

        $result = $this->pushFiles($user, $repoFullName, $branch, $allFiles, $commitMessage, [
            'audit_type' => 'Project',
            'audit_id' => (string) $project->id,
        ]);

        // Remember the repo/branch the user pushed to as this project's GitHub target
        if ($project->github_repo !== $result['repo'] || $project->github_branch !== $result['branch']) {
            $project->update(['github_repo' => $result['repo'], 'github_branch' => $result['branch']]);
        }

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
        $normalizedRepo = $this->normalizeRepo($repoFullName);
        $branch = trim($branch);

        if ($normalizedRepo === null) {
            throw new Exception("Repositori tidak valid: {$repoFullName}. Gunakan link https://github.com/pemilik/nama-repo atau format pemilik/nama-repo.");
        }
        $repoFullName = $normalizedRepo;

        if ($branch === '') {
            // No branch named: use the repository's default branch (main for a brand-new empty repo)
            $repoInfo = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'XZY-Platform',
            ])->timeout(30)->get("https://api.github.com/repos/{$repoFullName}");
            $branch = $repoInfo->successful() ? ($repoInfo->json('default_branch') ?: 'main') : 'main';
        }

        if (!$this->isValidBranchName($branch)) {
            throw new Exception("Nama branch tidak valid: {$branch}");
        }

        // The XZY app's own source repository is reserved for administrators
        if (strtolower($repoFullName) === 'igustisultanh12/ai' && !$user->isAdmin()) {
            throw new Exception("Repositori sistem XZY hanya dapat menjadi target push oleh administrator. Harap pilih repositori GitHub pribadi Anda.");
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

        $deletePaths = [];
        foreach ((array) ($context['delete'] ?? []) as $path) {
            $clean = $this->sanitizePath((string) $path);
            if ($clean === null) {
                throw new Exception("Path berkas yang akan dihapus tidak valid: {$path}");
            }
            if (!array_key_exists($clean, $filesToPush)) {
                $deletePaths[] = $clean;
            }
        }

        if (empty($filesToPush) && empty($deletePaths)) {
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

        // Check write access up front: GitHub answers 404 (not 403) on write endpoints
        // when the token may read a repo but not push to it.
        $repoInfo = $http()->get($api);
        if ($repoInfo->status() === 404 || $repoInfo->status() === 403) {
            throw new Exception($this->accessError($repoFullName, $repoInfo->status(), false));
        }
        if ($repoInfo->successful() && $repoInfo->json('permissions.push') === false) {
            throw new Exception($this->accessError($repoFullName, 403, true));
        }

        // 3. Resolve parent commit: target branch, else the repo default branch
        $parentCommitSha = null;
        $baseTreeSha = null;
        $branchExists = false;

        $repoEmpty = false;
        $refRes = $http()->get("{$api}/git/ref/heads/" . $this->encodeRef($branch));
        if ($refRes->successful()) {
            $branchExists = true;
            $parentCommitSha = $refRes->json('object.sha');
        } elseif ($refRes->status() === 409) {
            // "Git Repository is empty." — freshly created repo without any commit
            $repoEmpty = true;
        } elseif ($refRes->status() === 404) {
            $repoRes = $http()->get($api);
            if (!$repoRes->successful()) {
                throw new Exception("Repositori {$repoFullName} tidak ditemukan atau token GitHub Anda tidak punya akses ke repo itu (HTTP {$repoRes->status()}). Pastikan repo sudah dibuat dan, untuk fine-grained token, repo tersebut termasuk dalam akses token dengan izin Contents: Read and write.");
            }
            if (($repoRes->json('permissions.push') ?? true) === false) {
                throw new Exception("Token GitHub Anda tidak punya izin menulis (push) ke {$repoFullName}.");
            }
            $defaultBranch = $repoRes->json('default_branch');
            if ($defaultBranch) {
                $baseRef = $http()->get("{$api}/git/ref/heads/" . $this->encodeRef($defaultBranch));
                if ($baseRef->successful()) {
                    $parentCommitSha = $baseRef->json('object.sha');
                } elseif ($baseRef->status() === 409 || $baseRef->status() === 404) {
                    $repoEmpty = (int) ($repoRes->json('size') ?? 0) === 0 || $baseRef->status() === 409;
                }
            } else {
                $repoEmpty = true;
            }
        } else {
            throw new Exception("Gagal membaca branch {$branch} (HTTP {$refRes->status()}): " . ($refRes->json('message') ?? $refRes->body()));
        }

        // Empty repository: the Git Data API refuses blobs until a first commit exists.
        // Create that first commit with the Contents API, then continue normally.
        if ($repoEmpty) {
            if (empty($filesToPush)) {
                throw new Exception("Repositori {$repoFullName} masih kosong, tidak ada berkas yang bisa dihapus.");
            }
            $firstPath = array_key_first($filesToPush);
            $initRes = $http()->put("{$api}/contents/" . $this->encodeRef($firstPath), [
                'message' => $commitMessage,
                'content' => base64_encode($filesToPush[$firstPath]),
                'branch' => $branch,
            ]);

            if (in_array($initRes->status(), [403, 404], true)) {
                throw new Exception($this->accessError($repoFullName, $initRes->status(), true));
            }
            if (!$initRes->successful()) {
                throw new Exception("Gagal membuat commit awal di repositori kosong {$repoFullName} (HTTP {$initRes->status()}): " . ($initRes->json('message') ?? $initRes->body()));
            }

            $parentCommitSha = $initRes->json('commit.sha');
            $baseTreeSha = $initRes->json('commit.tree.sha');
            $branchExists = true;
            $pushedFirst = [$firstPath];
            unset($filesToPush[$firstPath]);

            if (empty($filesToPush)) {
                return $this->finishPush($user, $repoFullName, $branch, $parentCommitSha, $pushedFirst, true, $context);
            }
        }

        if ($parentCommitSha && !$baseTreeSha) {
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

            if (in_array($blobRes->status(), [403, 404], true)) {
                throw new Exception($this->accessError($repoFullName, $blobRes->status(), true));
            }
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

        foreach ($deletePaths as $path) {
            $treeItems[] = ['path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => null];
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

        if (in_array($updateRefRes->status(), [403, 404], true) && !str_contains((string) $updateRefRes->json('message'), 'fast')) {
            throw new Exception($this->accessError($repoFullName, $updateRefRes->status(), true));
        }
        if (!$updateRefRes->successful()) {
            $errorBody = (string) ($updateRefRes->json('message') ?? $updateRefRes->body());
            if (stripos($errorBody, 'fast forward') !== false || stripos($errorBody, 'fast-forward') !== false || $updateRefRes->status() === 409) {
                throw new Exception("Konflik remote terdeteksi (non-fast-forward). Branch {$branch} berubah selama push. Coba push ulang.");
            }
            throw new Exception("Gagal memperbarui referensi branch GitHub: " . $errorBody);
        }

        return $this->finishPush(
            $user,
            $repoFullName,
            $branch,
            $newCommitSha,
            array_merge($pushedFirst ?? [], array_keys($filesToPush)),
            !$branchExists || $repoEmpty,
            $context,
            $deletePaths
        );
    }

    protected function finishPush(
        User $user,
        string $repoFullName,
        string $branch,
        string $commitSha,
        array $paths,
        bool $branchCreated,
        array $context,
        array $deleted = []
    ): array {
        $this->audit->log('github.push', $user, $context['audit_type'] ?? 'GitHub', $context['audit_id'] ?? $repoFullName, [
            'repo' => $repoFullName,
            'branch' => $branch,
            'commit_sha' => $commitSha,
            'files' => count($paths),
            'branch_created' => $branchCreated,
        ]);

        return [
            'success' => true,
            'commit_sha' => $commitSha,
            'commit_url' => "https://github.com/{$repoFullName}/commit/{$commitSha}",
            'repo' => $repoFullName,
            'repo_url' => "https://github.com/{$repoFullName}",
            'branch' => $branch,
            'branch_created' => $branchCreated,
            'files' => $paths,
            'deleted' => $deleted,
            'message' => "Berhasil push " . count($paths) . " berkas" . ($deleted ? ", hapus " . count($deleted) . " berkas" : '') . " ke {$repoFullName}:{$branch}",
        ];
    }

    /**
     * List every file in a repository branch (recursive git tree).
     *
     * @return array{repo: string, branch: string, files: array<int, array{path: string, size: int}>, truncated: bool, empty: bool}
     */
    public function getRepositoryTree(User $user, string $repo, ?string $branch = null, int $limit = 1000): array
    {
        [$repoFullName, $http] = $this->repoClient($user, $repo);
        $api = "https://api.github.com/repos/{$repoFullName}";

        $repoInfo = $http()->get($api);
        if (!$repoInfo->successful()) {
            throw new Exception($this->accessError($repoFullName, $repoInfo->status(), false));
        }
        $canPush = $repoInfo->json('permissions.push');
        if (!$branch) {
            $branch = $repoInfo->json('default_branch') ?: 'main';
        }

        $res = $http()->get("{$api}/git/trees/" . rawurlencode($branch), ['recursive' => 1]);
        if ($res->status() === 409) {
            return ['repo' => $repoFullName, 'branch' => $branch, 'can_push' => $canPush, 'files' => [], 'truncated' => false, 'empty' => true];
        }
        if (!$res->successful()) {
            throw new Exception("Gagal membaca isi {$repoFullName}@{$branch} (HTTP {$res->status()}): " . ($res->json('message') ?? $res->body()));
        }

        $files = [];
        foreach ((array) $res->json('tree') as $entry) {
            if (($entry['type'] ?? '') === 'blob') {
                $files[] = ['path' => $entry['path'], 'size' => (int) ($entry['size'] ?? 0)];
            }
        }

        $truncated = (bool) $res->json('truncated') || count($files) > $limit;

        return [
            'repo' => $repoFullName,
            'branch' => $branch,
            // false = the connected token cannot push here; tell the user before writing files
            'can_push' => $canPush,
            'files' => array_slice($files, 0, $limit),
            'truncated' => $truncated,
            'empty' => false,
        ];
    }

    /**
     * Read text files from a repository branch.
     *
     * @return array<string, array{content?: string, error?: string, size?: int}>
     */
    public function readRepositoryFiles(User $user, string $repo, array $paths, ?string $branch = null, int $maxBytesPerFile = 150000): array
    {
        [$repoFullName, $http] = $this->repoClient($user, $repo);
        $api = "https://api.github.com/repos/{$repoFullName}";

        $out = [];
        foreach (array_slice(array_values(array_unique($paths)), 0, 30) as $path) {
            $clean = $this->sanitizePath((string) $path);
            if ($clean === null) {
                $out[(string) $path] = ['error' => 'Path tidak valid'];
                continue;
            }

            $query = $branch ? ['ref' => $branch] : [];
            $res = $http()->withHeaders(['Accept' => 'application/vnd.github.raw+json'])
                ->get("{$api}/contents/" . $this->encodeRef($clean), $query);

            if (!$res->successful()) {
                $out[$clean] = ['error' => "HTTP {$res->status()}: " . ($res->json('message') ?? 'tidak dapat dibaca')];
                continue;
            }

            $body = $res->body();
            if (str_contains(substr($body, 0, 8000), "\0")) {
                $out[$clean] = ['error' => 'Berkas biner, tidak ditampilkan', 'size' => strlen($body)];
                continue;
            }
            if (strlen($body) > $maxBytesPerFile) {
                $out[$clean] = [
                    'content' => substr($body, 0, $maxBytesPerFile),
                    'size' => strlen($body),
                    'error' => 'Berkas terpotong karena terlalu besar',
                ];
                continue;
            }

            $out[$clean] = ['content' => $body, 'size' => strlen($body)];
        }

        return $out;
    }

    /**
     * Create a repository for the authenticated user.
     */
    public function createRepository(User $user, string $name, bool $private = true, ?string $description = null): array
    {
        $token = $this->getUserToken($user);
        $name = trim($name);
        if (!preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $name)) {
            throw new Exception("Nama repositori tidak valid: {$name}");
        }

        $res = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'XZY-Platform',
        ])->timeout(30)->post('https://api.github.com/user/repos', array_filter([
            'name' => $name,
            'private' => $private,
            'description' => $description,
            'auto_init' => true,
        ], fn ($v) => $v !== null));

        if (!$res->successful()) {
            $msg = $res->json('errors.0.message') ?? $res->json('message') ?? $res->body();
            if ($res->status() === 403) {
                $msg .= ' (token perlu izin membuat repo: scope "repo" untuk classic token, atau "Administration: Read and write" untuk fine-grained token)';
            }
            throw new Exception("Gagal membuat repositori {$name} (HTTP {$res->status()}): {$msg}");
        }

        $this->audit->log('github.repo_created', $user, 'GitHub', (string) $res->json('full_name'), [
            'private' => $private,
        ]);

        return [
            'repo' => $res->json('full_name'),
            'url' => $res->json('html_url'),
            'default_branch' => $res->json('default_branch') ?: 'main',
            'private' => (bool) $res->json('private'),
        ];
    }

    /**
     * @return array{0: string, 1: \Closure}
     */
    protected function repoClient(User $user, string $repo): array
    {
        $token = $this->getUserToken($user);
        $repoFullName = $this->normalizeRepo($repo);
        if ($repoFullName === null) {
            throw new Exception("Repositori tidak valid: {$repo}. Gunakan link https://github.com/pemilik/nama-repo atau pemilik/nama-repo.");
        }

        $http = fn () => Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'XZY-Platform',
        ])->timeout(30);

        return [$repoFullName, $http];
    }

    protected function defaultBranch(\Closure $http, string $repoFullName): string
    {
        $res = $http()->get("https://api.github.com/repos/{$repoFullName}");
        if (!$res->successful()) {
            throw new Exception("Repositori {$repoFullName} tidak ditemukan atau token GitHub Anda tidak punya akses (HTTP {$res->status()}).");
        }
        return $res->json('default_branch') ?: 'main';
    }

    /**
     * Actionable message when the connected token cannot read or write a repository.
     */
    public function accessError(string $repoFullName, int $status, bool $canRead): string
    {
        $what = $canRead
            ? "Token GitHub yang terhubung bisa membaca {$repoFullName} tetapi TIDAK punya izin menulis/push (HTTP {$status})."
            : "Repositori {$repoFullName} tidak ditemukan atau token GitHub yang terhubung tidak punya akses (HTTP {$status}).";

        return $what . ' Perbaiki di GitHub: fine-grained token -> Repository access harus mencakup ' . $repoFullName
            . ' dan Permissions -> Contents: Read and write; classic token -> centang scope "repo". '
            . 'Lalu hubungkan ulang token di menu Pengaturan & GitHub. Ini masalah izin token, bukan format URL.';
    }

    /**
     * Accept "owner/repo", "https://github.com/owner/repo(.git)", "github.com/owner/repo/tree/x"
     * or "git@github.com:owner/repo.git" and return "owner/repo" (null when invalid).
     */
    public function normalizeRepo(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        if (preg_match('#^(?:https?://)?(?:www\.)?github\.com[/:]([A-Za-z0-9-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?(?:/.*)?$#i', $input, $m)
            || preg_match('#^git@github\.com:([A-Za-z0-9-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?$#i', $input, $m)
            || preg_match('#^([A-Za-z0-9-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?$#', $input, $m)) {
            if ($m[2] === '.' || $m[2] === '..') {
                return null;
            }
            return $m[1] . '/' . $m[2];
        }

        return null;
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
