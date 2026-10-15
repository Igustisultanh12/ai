<?php

namespace App\Services\Agent;

use App\Models\Project;
use App\Services\Usage\TokenAccountingService;

class ContextManager
{
    protected TokenAccountingService $tokenAccounting;

    public function __construct(TokenAccountingService $tokenAccounting)
    {
        $this->tokenAccounting = $tokenAccounting;
    }

    /**
     * Build budgeted context for a project to feed into the AI agent prompt.
     */
    public function buildProjectContext(Project $project, string $userPrompt, int $maxTokens = 8000): string
    {
        $context = [];
        $context[] = "=== INFORMASI PROYEK ===";
        $context[] = "Nama: {$project->name}";
        $context[] = "Deskripsi: " . ($project->description ?: 'Tidak ada deskripsi');
        $context[] = "Template: {$project->template}";

        $files = $project->files()->get();
        $fileList = $files->pluck('path')->toArray();
        $context[] = "\n=== DAFTAR BERKAS PROYEK (" . count($fileList) . " berkas) ===";
        $context[] = implode("\n", $fileList);

        // Identify key files to include in context
        $keyFiles = ['resources/js/App.vue', 'resources/js/app.js', 'package.json', 'README.md'];
        
        // Match files relevant to prompt
        $words = array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9_\-\.]/', ' ', $userPrompt))));
        foreach ($files as $file) {
            foreach ($words as $word) {
                if (strlen($word) >= 3 && str_contains(strtolower($file->path), $word)) {
                    if (!in_array($file->path, $keyFiles, true)) {
                        $keyFiles[] = $file->path;
                    }
                    break;
                }
            }
        }

        $context[] = "\n=== BERKAS UTAMA & RELEVAN ===";
        $currentTokens = $this->tokenAccounting->estimateTokenCount(implode("\n", $context));

        foreach ($keyFiles as $relPath) {
            $file = $files->firstWhere('path', $relPath);
            if (!$file || empty($file->content)) {
                continue;
            }

            $fileHeader = "\n--- FILE: {$relPath} ---";
            $fileTokens = $this->tokenAccounting->estimateTokenCount($fileHeader . "\n" . $file->content);

            if (($currentTokens + $fileTokens) < $maxTokens) {
                $context[] = $fileHeader;
                $context[] = $file->content;
                $currentTokens += $fileTokens;
            } else {
                // Budget reached
                $context[] = "\n[Catatan: Berkas lainnya dipotong karena batas konteks token]";
                break;
            }
        }

        return implode("\n", $context);
    }
}
