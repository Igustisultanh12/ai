<?php

namespace App\Services\Exports;

use App\Models\Project;
use App\Models\ProjectExport;
use App\Services\Sandbox\WorkspaceManager;
use Exception;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RarExportService
{
    protected WorkspaceManager $workspaceManager;

    public function __construct(WorkspaceManager $workspaceManager)
    {
        $this->workspaceManager = $workspaceManager;
    }

    /**
     * Get configured RAR binary path or discover it from common OS locations.
     */
    public function getRarBinaryPath(): ?string
    {
        $configured = config('services.rar.binary_path') ?: env('RAR_BINARY_PATH');
        if (!empty($configured) && file_exists($configured)) {
            return $configured;
        }

        $candidates = [
            'C:\\Program Files\\WinRAR\\Rar.exe',
            'C:\\Program Files (x86)\\WinRAR\\Rar.exe',
            '/usr/bin/rar',
            '/usr/local/bin/rar',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        // Check if `rar` command exists on PATH
        $whichCmd = PHP_OS_FAMILY === 'Windows' ? ['where', 'rar.exe'] : ['which', 'rar'];
        try {
            $process = new Process($whichCmd);
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim(explode("\n", $process->getOutput())[0]);
                if (!empty($path) && file_exists($path)) {
                    return $path;
                }
            }
        } catch (\Throwable $e) {
            // Ignore failure
        }

        return null;
    }

    /**
     * Readiness check for RAR export functionality.
     */
    public function getReadinessStatus(): array
    {
        $binary = $this->getRarBinaryPath();

        if (!$binary) {
            return [
                'is_ready' => false,
                'status' => 'not_installed',
                'binary_path' => null,
                'message' => 'Binary pembuat arsip RAR resmi belum terpasang atau belum dikonfigurasi pada server.',
                'instructions' => 'Fitur ekspor RAR memerlukan binary RAR/WinRAR command-line resmi (misalnya rar pada Linux atau Rar.exe pada Windows). Pastikan lisensi sesuai, pasang binary tersebut, lalu tentukan path melalui variabel RAR_BINARY_PATH pada berkas .env Anda.',
            ];
        }

        return [
            'is_ready' => true,
            'status' => 'ready',
            'binary_path' => $binary,
            'message' => 'Binary RAR siap digunakan: ' . $binary,
            'instructions' => null,
        ];
    }

    /**
     * Create a genuine RAR archive using official RAR command line executable.
     */
    public function export(Project $project, ProjectExport $export): ProjectExport
    {
        $readiness = $this->getReadinessStatus();
        if (!$readiness['is_ready']) {
            throw new Exception("Ekspor RAR belum siap: " . $readiness['message'] . " " . $readiness['instructions']);
        }

        $binary = $readiness['binary_path'];
        $workspaceDir = $this->workspaceManager->getWorkspacePath($project->id);

        $exportDir = storage_path('app/exports');
        if (!File::exists($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        $targetRarPath = "{$exportDir}/{$export->id}.rar";

        // Structured argv command: rar a -r -ep1 -x*.env -x*node_modules* -x*vendor* -x*.git* target.rar workspace/*
        $command = [
            $binary,
            'a',
            '-r',
            '-ep1',
            '-x*.env',
            '-x*.git*',
            '-x*node_modules*',
            '-x*vendor*',
            $targetRarPath,
            $workspaceDir . DIRECTORY_SEPARATOR . '*',
        ];

        $process = new Process($command, $workspaceDir, null, null, 60);
        $process->run();

        if (!$process->isSuccessful() || !file_exists($targetRarPath)) {
            throw new Exception("Pembuatan arsip RAR gagal: " . $process->getErrorOutput() . " " . $process->getOutput());
        }

        $size = filesize($targetRarPath);
        $checksum = hash_file('sha256', $targetRarPath);

        $export->update([
            'status' => 'ready',
            'file_path' => $targetRarPath,
            'file_size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'expires_at' => now()->addDays(2),
        ]);

        return $export;
    }
}
