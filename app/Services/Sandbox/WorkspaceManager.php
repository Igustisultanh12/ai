<?php

namespace App\Services\Sandbox;

use App\Models\Project;
use App\Models\ProjectCheckpoint;
use App\Models\ProjectFile;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkspaceManager
{
    protected string $storageDisk = 'local';

    /**
     * Get the base directory path for a project workspace.
     */
    public function getWorkspacePath(string $projectId): string
    {
        $dir = storage_path("app/workspaces/{$projectId}");
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        return realpath($dir) ?: $dir;
    }

    /**
     * Validate that a relative path is canonical, safe, and stays inside the project workspace.
     * Prevents path traversal (../), absolute path breakout, null bytes, and symlink escapes.
     */
    public function validateSafePath(string $projectId, string $relativePath): string
    {
        // 1. Block null byte injection
        if (str_contains($relativePath, "\0")) {
            throw new Exception("Path tidak valid: terdeteksi null byte.");
        }

        // 2. Normalize separators
        $normalized = str_replace('\\', '/', trim($relativePath));
        $normalized = ltrim($normalized, '/');

        // 3. Block directory traversal components
        $parts = explode('/', $normalized);
        foreach ($parts as $part) {
            if ($part === '..' || $part === '.') {
                throw new Exception("Path traversal terdeteksi pada path: {$relativePath}");
            }
        }

        // 4. Block access to host root or sensitive files
        if (preg_match('/^[a-zA-Z]:/', $normalized) || str_starts_with($normalized, '//')) {
            throw new Exception("Absolute path tidak diizinkan: {$relativePath}");
        }

        $baseDir = $this->getWorkspacePath($projectId);
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);

        // 5. If file exists, check symlink escape
        if (file_exists($fullPath)) {
            $real = realpath($fullPath);
            if ($real === false || !str_starts_with($real, $baseDir)) {
                throw new Exception("Symlink escape terdeteksi: {$relativePath}");
            }
        }

        return $normalized;
    }

    /**
     * Write or update a file in the workspace and synchronize database record.
     */
    public function putFile(Project $project, string $relativePath, string $content): ProjectFile
    {
        $safePath = $this->validateSafePath($project->id, $relativePath);
        $baseDir = $this->getWorkspacePath($project->id);
        $targetFile = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safePath);

        $dir = dirname($targetFile);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($targetFile, $content);
        $hash = hash('sha256', $content);
        $size = strlen($content);

        return ProjectFile::updateOrCreate(
            ['project_id' => $project->id, 'path' => $safePath],
            [
                'content' => $content,
                'size_bytes' => $size,
                'is_binary' => false,
                'mime_type' => $this->detectMimeType($safePath),
                'content_hash' => $hash,
            ]
        );
    }

    /**
     * Read a file safely from the project workspace.
     */
    public function getFile(Project $project, string $relativePath): string
    {
        $safePath = $this->validateSafePath($project->id, $relativePath);
        $baseDir = $this->getWorkspacePath($project->id);
        $targetFile = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safePath);

        if (!File::exists($targetFile)) {
            throw new Exception("Berkas tidak ditemukan: {$safePath}");
        }

        return File::get($targetFile);
    }

    /**
     * Delete a file safely from workspace and database.
     */
    public function deleteFile(Project $project, string $relativePath): bool
    {
        $safePath = $this->validateSafePath($project->id, $relativePath);
        $baseDir = $this->getWorkspacePath($project->id);
        $targetFile = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safePath);

        if (File::exists($targetFile)) {
            File::delete($targetFile);
        }

        ProjectFile::where('project_id', $project->id)->where('path', $safePath)->delete();
        return true;
    }

    /**
     * List all project files with path, size, and hash.
     */
    public function listFiles(Project $project): array
    {
        return ProjectFile::where('project_id', $project->id)
            ->select(['id', 'path', 'size_bytes', 'is_binary', 'mime_type', 'updated_at'])
            ->orderBy('path', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Create an atomic snapshot checkpoint of the current workspace state.
     */
    public function createCheckpoint(Project $project, int $userId, string $message, ?string $buildStatus = null, ?string $testsStatus = null): ProjectCheckpoint
    {
        $files = ProjectFile::where('project_id', $project->id)->get();
        $snapshot = [];

        foreach ($files as $file) {
            $snapshot[$file->path] = [
                'content' => $file->content,
                'size_bytes' => $file->size_bytes,
                'content_hash' => $file->content_hash,
            ];
        }

        $checkpoint = ProjectCheckpoint::create([
            'project_id' => $project->id,
            'user_id' => $userId,
            'commit_message' => $message,
            'snapshot' => $snapshot,
            'file_count' => count($snapshot),
            'build_status' => $buildStatus,
            'tests_status' => $testsStatus,
        ]);

        $project->update(['active_checkpoint_id' => $checkpoint->id]);
        return $checkpoint;
    }

    /**
     * Restore workspace files from a previous checkpoint snapshot.
     */
    public function restoreCheckpoint(ProjectCheckpoint $checkpoint): bool
    {
        $project = $checkpoint->project;
        $snapshot = $checkpoint->snapshot;

        // Clear existing workspace directory
        $baseDir = $this->getWorkspacePath($project->id);
        File::cleanDirectory($baseDir);
        ProjectFile::where('project_id', $project->id)->delete();

        foreach ($snapshot as $path => $fileData) {
            $this->putFile($project, $path, $fileData['content'] ?? '');
        }

        $project->update(['active_checkpoint_id' => $checkpoint->id]);
        return true;
    }

    /**
     * Initialize standard Laravel + Vue 3 starter template.
     */
    public function initializeStarterTemplate(Project $project): void
    {
        $templateFiles = [
            'README.md' => "# {$project->name}\n\n{$project->description}\n\nAplikasi modern dibangun menggunakan Laravel dan Vue 3 pada platform vibecoding XZY.\n",
            'package.json' => json_encode([
                'name' => Str::slug($project->name),
                'private' => true,
                'type' => 'module',
                'scripts' => [
                    'dev' => 'vite',
                    'build' => 'vite build',
                    'test' => 'echo "No frontend tests specified"'
                ],
                'dependencies' => [
                    'vue' => '^3.5.0',
                ],
                'devDependencies' => [
                    '@vitejs/plugin-vue' => '^5.2.0',
                    'vite' => '^6.0.0',
                    'tailwindcss' => '^4.0.0'
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'composer.json' => json_encode([
                'name' => 'app/' . Str::slug($project->name),
                'type' => 'project',
                'description' => $project->description ?: 'XZY Project',
                'require' => [
                    'php' => '^8.2',
                    'laravel/framework' => '^11.0'
                ],
                'scripts' => [
                    'test' => 'php artisan test'
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'vite.config.js' => "import { defineConfig } from 'vite';\nimport vue from '@vitejs/plugin-vue';\n\nexport default defineConfig({\n  plugins: [vue()],\n});\n",
            'resources/js/app.js' => "import { createApp } from 'vue';\nimport App from './App.vue';\n\ncreateApp(App).mount('#app');\n",
            'resources/js/App.vue' => "<template>\n  <div class=\"min-h-screen bg-slate-900 text-white flex flex-col items-center justify-center p-6\">\n    <div class=\"max-w-xl text-center space-y-4\">\n      <div class=\"inline-block px-3 py-1 bg-violet-600/30 text-violet-400 border border-violet-500/30 rounded-full text-xs font-semibold tracking-wide\">\n        Vibecoding XZY\n      </div>\n      <h1 class=\"text-4xl font-extrabold tracking-tight\">" . htmlspecialchars($project->name) . "</h1>\n      <p class=\"text-slate-400\">Aplikasi siap dijalankan dan diperluas melalui chat vibecoding.</p>\n      <button @click=\"count++\" class=\"px-5 py-2.5 bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-semibold rounded-lg transition-colors\">\n        Hitungan Interaktif: {{ count }}\n      </button>\n    </div>\n  </div>\n</template>\n\n<script setup>\nimport { ref } from 'vue';\nconst count = ref(0);\n</script>\n",
            'public/index.html' => "<!DOCTYPE html>\n<html lang=\"id\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>" . htmlspecialchars($project->name) . "</title>\n  <script src=\"https://cdn.tailwindcss.com\"></script>\n</head>\n<body class=\"bg-slate-950 text-slate-100\">\n  <div id=\"app\"></div>\n  <script type=\"module\" src=\"/resources/js/app.js\"></script>\n</body>\n</html>\n",
            '.gitignore' => "/vendor\n/node_modules\n.env\n.env.*\n.DS_Store\n/dist\n/storage/*.key\n",
            '.env.example' => "APP_NAME=\"" . addslashes($project->name) . "\"\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://localhost\n\nDB_CONNECTION=sqlite\nDB_DATABASE=database/database.sqlite\n",
        ];

        foreach ($templateFiles as $path => $content) {
            $this->putFile($project, $path, $content);
        }

        $this->createCheckpoint($project, $project->user_id, 'Inisialisasi template proyek awal (Laravel + Vue 3)', 'passed', 'passed');
    }

    private function detectMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'vue' => 'text/x-vue',
            'js', 'ts' => 'text/javascript',
            'json' => 'application/json',
            'php' => 'text/x-php',
            'html' => 'text/html',
            'css' => 'text/css',
            'md' => 'text/markdown',
            default => 'text/plain',
        };
    }
}
