<?php

namespace App\Services\Agent;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\GitHubService;
use App\Services\Sandbox\WorkspaceManager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tools the chat model can call (OpenAI-compatible function calling through
 * TokenomixAI /v1/chat/completions): read GitHub repos, clone a repo into a
 * project, edit project files, and commit/push with the user's own GitHub token.
 */
class ChatToolbox
{
    public const MAX_RESULT_CHARS = 60000;

    public function __construct(
        protected GitHubService $github,
        protected WorkspaceManager $workspace
    ) {
    }

    public function definitions(): array
    {
        $fn = fn (string $name, string $description, array $properties = [], array $required = []) => [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => empty($properties) ? new \stdClass() : $properties,
                    'required' => $required,
                ],
            ],
        ];

        $repo = ['type' => 'string', 'description' => 'Repositori GitHub: link https://github.com/pemilik/repo atau pemilik/repo'];
        $branch = ['type' => 'string', 'description' => 'Nama branch (opsional, default: default branch repo)'];
        $files = [
            'type' => 'array',
            'description' => 'Berkas lengkap yang ditulis (isi penuh, bukan potongan)',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Path relatif, mis. src/App.vue'],
                    'content' => ['type' => 'string', 'description' => 'Isi lengkap berkas'],
                ],
                'required' => ['path', 'content'],
            ],
        ];

        return [
            $fn('github_list_repos', 'Daftar repositori GitHub milik/akses pengguna.'),
            $fn('github_list_files', 'Daftar semua berkas (path + ukuran) di sebuah repositori GitHub. Gunakan sebelum membaca atau mengubah repo.', [
                'repo' => $repo,
                'branch' => $branch,
            ], ['repo']),
            $fn('github_read_files', 'Membaca isi beberapa berkas teks dari repositori GitHub (maks 30 berkas per panggilan).', [
                'repo' => $repo,
                'paths' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Daftar path berkas'],
                'branch' => $branch,
            ], ['repo', 'paths']),
            $fn('github_push_files', 'Commit & push berkas langsung ke repositori GitHub dalam satu commit (tanpa force push). Branch yang belum ada dibuat otomatis. Hanya gunakan bila pengguna meminta push/commit/simpan ke GitHub.', [
                'repo' => $repo,
                'branch' => $branch,
                'commit_message' => ['type' => 'string', 'description' => 'Pesan commit yang jelas'],
                'files' => $files,
                'delete_paths' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Path berkas yang dihapus (opsional)'],
            ], ['repo', 'commit_message', 'files']),
            $fn('github_create_repo', 'Membuat repositori GitHub baru di akun pengguna. Hanya bila pengguna meminta membuat repo.', [
                'name' => ['type' => 'string', 'description' => 'Nama repo, mis. project-a'],
                'private' => ['type' => 'boolean', 'description' => 'Repo privat (default true)'],
                'description' => ['type' => 'string'],
            ], ['name']),
            $fn('clone_repo_to_project', 'Clone/impor repositori GitHub menjadi proyek XZY (berkas teks disalin ke workspace) dan hubungkan percakapan ini ke proyek tersebut.', [
                'repo' => $repo,
                'branch' => $branch,
                'project_name' => ['type' => 'string', 'description' => 'Nama proyek (default: nama repo)'],
            ], ['repo']),
            $fn('project_list_files', 'Daftar berkas proyek XZY yang terhubung ke percakapan ini.'),
            $fn('project_read_files', 'Membaca isi berkas proyek XZY yang terhubung.', [
                'paths' => ['type' => 'array', 'items' => ['type' => 'string']],
            ], ['paths']),
            $fn('project_write_files', 'Menulis/mengubah berkas di proyek XZY yang terhubung (checkpoint dibuat otomatis sebelum perubahan).', [
                'files' => $files,
                'delete_paths' => ['type' => 'array', 'items' => ['type' => 'string']],
            ], ['files']),
            $fn('project_push_to_github', 'Push seluruh berkas proyek XZY yang terhubung ke GitHub. Repo baru akan disimpan sebagai target proyek.', [
                'repo' => ['type' => 'string', 'description' => 'Link/nama repo tujuan (opsional jika proyek sudah punya target)'],
                'branch' => $branch,
                'commit_message' => ['type' => 'string'],
            ]),
        ];
    }

    /**
     * Human-readable label for the activity log shown in chat.
     */
    public function describe(string $name, array $args): string
    {
        $repo = $this->github->normalizeRepo((string) ($args['repo'] ?? '')) ?? ($args['repo'] ?? '');
        $count = fn ($key) => is_array($args[$key] ?? null) ? count($args[$key]) : 0;

        return match ($name) {
            'github_list_repos' => 'Melihat daftar repositori GitHub',
            'github_list_files' => "Melihat isi repo {$repo}",
            'github_read_files' => "Membaca {$count('paths')} berkas dari {$repo}",
            'github_push_files' => "Push {$count('files')} berkas ke {$repo}" . (!empty($args['branch']) ? ":{$args['branch']}" : ''),
            'github_create_repo' => 'Membuat repo ' . ($args['name'] ?? ''),
            'clone_repo_to_project' => "Clone {$repo} ke proyek",
            'project_list_files' => 'Melihat berkas proyek',
            'project_read_files' => "Membaca {$count('paths')} berkas proyek",
            'project_write_files' => "Menulis {$count('files')} berkas proyek",
            'project_push_to_github' => 'Push proyek ke GitHub' . ($repo ? " {$repo}" : ''),
            default => $name,
        };
    }

    /**
     * Execute a tool call. Always returns an array (errors become ['error' => ...]).
     */
    public function execute(string $name, array $args, User $user, Conversation $conversation): array
    {
        try {
            return match ($name) {
                'github_list_repos' => ['repos' => array_map(
                    fn ($r) => ['repo' => $r['full_name'], 'private' => $r['private'], 'default_branch' => $r['default_branch']],
                    $this->github->listRepositories($user)
                )],
                'github_list_files' => $this->github->getRepositoryTree($user, (string) ($args['repo'] ?? ''), $args['branch'] ?? null),
                'github_read_files' => [
                    'files' => $this->github->readRepositoryFiles($user, (string) ($args['repo'] ?? ''), (array) ($args['paths'] ?? []), $args['branch'] ?? null),
                ],
                'github_push_files' => $this->pushFiles($args, $user, $conversation),
                'github_create_repo' => $this->github->createRepository(
                    $user,
                    (string) ($args['name'] ?? ''),
                    (bool) ($args['private'] ?? true),
                    $args['description'] ?? null
                ),
                'clone_repo_to_project' => $this->cloneToProject($args, $user, $conversation),
                'project_list_files' => $this->projectListFiles($user, $conversation),
                'project_read_files' => $this->projectReadFiles($args, $user, $conversation),
                'project_write_files' => $this->projectWriteFiles($args, $user, $conversation),
                'project_push_to_github' => $this->projectPush($args, $user, $conversation),
                default => ['error' => "Tool tidak dikenal: {$name}"],
            };
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * JSON for the tool message, trimmed so it fits in the model context.
     */
    public function encodeResult(array $result): string
    {
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (strlen($json) > self::MAX_RESULT_CHARS) {
            $json = substr($json, 0, self::MAX_RESULT_CHARS) . '... [hasil dipotong karena terlalu panjang; baca lebih sedikit berkas per panggilan]';
        }
        return $json;
    }

    protected function filesArg(array $args): array
    {
        $files = [];
        foreach ((array) ($args['files'] ?? []) as $file) {
            if (!is_array($file) || !isset($file['path'])) {
                continue;
            }
            $files[(string) $file['path']] = (string) ($file['content'] ?? '');
        }
        return $files;
    }

    protected function pushFiles(array $args, User $user, Conversation $conversation): array
    {
        $files = $this->filesArg($args);
        return $this->github->pushFiles(
            $user,
            (string) ($args['repo'] ?? ''),
            (string) ($args['branch'] ?? ''),
            $files,
            trim((string) ($args['commit_message'] ?? '')) ?: 'Update dari chat XZY',
            [
                'audit_type' => 'Conversation',
                'audit_id' => (string) $conversation->id,
                'delete' => (array) ($args['delete_paths'] ?? []),
            ]
        );
    }

    protected function cloneToProject(array $args, User $user, Conversation $conversation): array
    {
        $tree = $this->github->getRepositoryTree($user, (string) ($args['repo'] ?? ''), $args['branch'] ?? null, 2000);
        $repo = $tree['repo'];

        $skip = fn (string $path, int $size) => $size > 300000
            || preg_match('#(^|/)(node_modules|vendor|\.git|dist|build)/#', $path)
            || preg_match('/\.(png|jpe?g|gif|webp|ico|bmp|pdf|zip|rar|7z|gz|tar|woff2?|ttf|otf|eot|mp[34]|mov|avi|exe|dll|so|dylib|lock)$/i', $path)
            || basename($path) === '.env';

        $wanted = [];
        $skipped = 0;
        foreach ($tree['files'] as $f) {
            if ($skip($f['path'], $f['size'])) {
                $skipped++;
            } else {
                $wanted[] = $f['path'];
            }
        }
        $wanted = array_slice($wanted, 0, 300);

        $name = trim((string) ($args['project_name'] ?? '')) ?: explode('/', $repo)[1];
        $project = Project::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'description' => "Clone dari https://github.com/{$repo}",
            'template' => 'laravel-vue3',
            'visibility' => 'private',
            'status' => 'active',
            'github_repo' => $repo,
            'github_branch' => $tree['branch'],
            'auto_push_github' => false,
        ]);

        $copied = 0;
        $errors = [];
        foreach (array_chunk($wanted, 30) as $chunk) {
            foreach ($this->github->readRepositoryFiles($user, $repo, $chunk, $tree['branch'], 300000) as $path => $file) {
                if (isset($file['content']) && empty($file['error'])) {
                    $this->workspace->putFile($project, $path, $file['content']);
                    $copied++;
                } else {
                    $errors[] = $path;
                }
            }
        }

        $this->workspace->createCheckpoint($project, $user->id, "Clone dari {$repo}@{$tree['branch']}");
        $conversation->update(['project_id' => $project->id]);

        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'repo' => $repo,
            'branch' => $tree['branch'],
            'files_copied' => $copied,
            'files_skipped' => $skipped + count($errors),
            'note' => 'Percakapan ini sekarang terhubung ke proyek tersebut. Gunakan project_* tools untuk mengubah berkas.',
        ];
    }

    protected function linkedProject(User $user, Conversation $conversation): Project
    {
        if (!$conversation->project_id) {
            throw new \RuntimeException('Percakapan ini belum terhubung ke proyek. Pilih proyek di header chat atau gunakan clone_repo_to_project.');
        }
        return Project::where('id', $conversation->project_id)->where('user_id', $user->id)->firstOrFail();
    }

    protected function projectListFiles(User $user, Conversation $conversation): array
    {
        $project = $this->linkedProject($user, $conversation);
        return [
            'project' => $project->name,
            'github_repo' => $project->github_repo,
            'files' => $project->files()->orderBy('path')->get(['path', 'size_bytes'])
                ->map(fn ($f) => ['path' => $f->path, 'size' => $f->size_bytes])->all(),
        ];
    }

    protected function projectReadFiles(array $args, User $user, Conversation $conversation): array
    {
        $project = $this->linkedProject($user, $conversation);
        $out = [];
        foreach (array_slice((array) ($args['paths'] ?? []), 0, 30) as $path) {
            $file = $project->files()->where('path', (string) $path)->first();
            $out[(string) $path] = $file ? ['content' => (string) $file->content] : ['error' => 'Berkas tidak ditemukan'];
        }
        return ['files' => $out];
    }

    protected function projectWriteFiles(array $args, User $user, Conversation $conversation): array
    {
        $project = $this->linkedProject($user, $conversation);
        $files = $this->filesArg($args);
        $deletes = (array) ($args['delete_paths'] ?? []);

        if (empty($files) && empty($deletes)) {
            return ['error' => 'Tidak ada berkas untuk ditulis.'];
        }

        $this->workspace->createCheckpoint($project, $user->id, 'Sebelum perubahan dari chat');

        $written = [];
        foreach ($files as $path => $content) {
            $clean = $this->github->sanitizePath($path);
            if ($clean === null) {
                return ['error' => "Path tidak valid: {$path}"];
            }
            $this->workspace->putFile($project, $clean, $content);
            $written[] = $clean;
        }

        $deleted = [];
        foreach ($deletes as $path) {
            $clean = $this->github->sanitizePath((string) $path);
            if ($clean !== null && $this->workspace->deleteFile($project, $clean)) {
                $deleted[] = $clean;
            }
        }

        return ['project' => $project->name, 'written' => $written, 'deleted' => $deleted];
    }

    protected function projectPush(array $args, User $user, Conversation $conversation): array
    {
        $project = $this->linkedProject($user, $conversation);
        return $this->github->pushProjectCheckpoint(
            $project,
            $user,
            trim((string) ($args['commit_message'] ?? '')) ?: ('Push dari XZY: ' . $project->name),
            !empty($args['branch']) ? (string) $args['branch'] : null,
            !empty($args['repo']) ? (string) $args['repo'] : null
        );
    }
}
