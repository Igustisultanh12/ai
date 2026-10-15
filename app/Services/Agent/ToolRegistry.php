<?php

namespace App\Services\Agent;

use App\Models\Project;
use App\Services\Sandbox\SandboxRunner;
use App\Services\Sandbox\WorkspaceManager;
use Exception;

class ToolRegistry
{
    protected WorkspaceManager $workspaceManager;
    protected SandboxRunner $sandboxRunner;

    public function __construct(WorkspaceManager $workspaceManager, SandboxRunner $sandboxRunner)
    {
        $this->workspaceManager = $workspaceManager;
        $this->sandboxRunner = $sandboxRunner;
    }

    /**
     * Get tool definitions in standard OpenAI function format.
     */
    public function getFunctionDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_files',
                    'description' => 'Melihat daftar berkas yang ada pada workspace proyek.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'directory' => [
                                'type' => 'string',
                                'description' => 'Subdirektori opsional untuk difilter',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'read_file',
                    'description' => 'Membaca isi berkas tertentu dari workspace.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path relatif berkas dalam workspace (misal: resources/js/App.vue)',
                            ],
                        ],
                        'required' => ['path'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_files',
                    'description' => 'Mencari berkas atau teks tertentu di seluruh proyek.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Kata kunci atau nama berkas yang dicari',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'apply_patch',
                    'description' => 'Mengusulkan penulisan atau perubahan berkas kode pada proyek.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path berkas yang akan dibuat atau diubah',
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'Seluruh isi berkas yang baru',
                            ],
                        ],
                        'required' => ['path', 'content'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'run_build',
                    'description' => 'Menjalankan verifikasi kompilasi dan sintaks berkas proyek dalam sandbox.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'run_tests',
                    'description' => 'Menjalankan suite pengujian kode dalam sandbox.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call safely.
     */
    public function execute(Project $project, string $name, array $args): array
    {
        return match ($name) {
            'list_files' => $this->toolListFiles($project, $args['directory'] ?? null),
            'read_file' => $this->toolReadFile($project, $args['path'] ?? ''),
            'search_files' => $this->toolSearchFiles($project, $args['query'] ?? ''),
            'apply_patch' => $this->toolApplyPatch($project, $args['path'] ?? '', $args['content'] ?? ''),
            'run_build' => $this->toolRunBuild($project),
            'run_tests' => $this->toolRunTests($project),
            default => [
                'success' => false,
                'error' => "Tool tidak dikenali: {$name}",
            ],
        };
    }

    protected function toolListFiles(Project $project, ?string $directory): array
    {
        $files = $this->workspaceManager->listFiles($project);
        if ($directory) {
            $dir = trim($directory, '/');
            $files = array_filter($files, fn ($f) => str_starts_with($f['path'], $dir));
        }

        return [
            'success' => true,
            'files' => array_column($files, 'path'),
            'count' => count($files),
        ];
    }

    protected function toolReadFile(Project $project, string $path): array
    {
        try {
            $content = $this->workspaceManager->getFile($project, $path);
            return [
                'success' => true,
                'path' => $path,
                'content' => $content,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function toolSearchFiles(Project $project, string $query): array
    {
        $matches = [];
        $files = $project->files;
        $q = strtolower($query);

        foreach ($files as $file) {
            if (str_contains(strtolower($file->path), $q)) {
                $matches[] = [
                    'path' => $file->path,
                    'match_type' => 'filename',
                ];
            } elseif ($file->content && str_contains(strtolower($file->content), $q)) {
                $matches[] = [
                    'path' => $file->path,
                    'match_type' => 'content',
                ];
            }
        }

        return [
            'success' => true,
            'query' => $query,
            'matches' => $matches,
            'count' => count($matches),
        ];
    }

    protected function toolApplyPatch(Project $project, string $path, string $content): array
    {
        try {
            $safePath = $this->workspaceManager->validateSafePath($project->id, $path);
            $this->workspaceManager->putFile($project, $safePath, $content);

            return [
                'success' => true,
                'path' => $safePath,
                'message' => "Berkas {$safePath} berhasil diperbarui.",
                'size_bytes' => strlen($content),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'path' => $path,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function toolRunBuild(Project $project): array
    {
        return $this->sandboxRunner->verifyProject($project);
    }

    protected function toolRunTests(Project $project): array
    {
        return $this->sandboxRunner->verifyProject($project);
    }
}
