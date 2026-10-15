<?php

namespace App\Services\Exports;

use App\Models\Project;
use App\Models\ProjectExport;
use App\Services\Sandbox\WorkspaceManager;
use Exception;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ZipExportService
{
    protected WorkspaceManager $workspaceManager;

    public function __construct(WorkspaceManager $workspaceManager)
    {
        $this->workspaceManager = $workspaceManager;
    }

    /**
     * Create a genuine ZIP archive of the project workspace.
     */
    public function export(Project $project, ProjectExport $export): ProjectExport
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception("Ekstensi PHP ZipArchive tidak tersedia pada server.");
        }

        $exportDir = storage_path('app/exports');
        if (!File::exists($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        $targetZipPath = "{$exportDir}/{$export->id}.zip";
        $zip = new ZipArchive();

        $openResult = $zip->open($targetZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new Exception("Gagal membuat arsip ZIP (Kode error: {$openResult}).");
        }

        $files = $project->files()->get();
        $excludedKeywords = ['.env', 'node_modules', 'vendor', '.git', '.DS_Store'];

        foreach ($files as $file) {
            $path = $file->path;

            // Check exclusions
            $shouldExclude = false;
            foreach ($excludedKeywords as $ex) {
                if (str_contains($path, $ex) && basename($path) !== '.env.example') {
                    $shouldExclude = true;
                    break;
                }
            }

            if (!$shouldExclude) {
                $content = $file->content ?? '';
                $zip->addFromString($path, $content);
            }
        }

        $zip->close();

        if (!file_exists($targetZipPath)) {
            throw new Exception("Arsip ZIP tidak berhasil disimpan ke storage.");
        }

        $size = filesize($targetZipPath);
        $checksum = hash_file('sha256', $targetZipPath);

        $export->update([
            'status' => 'ready',
            'file_path' => $targetZipPath,
            'file_size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'expires_at' => now()->addDays(2),
        ]);

        return $export;
    }
}
