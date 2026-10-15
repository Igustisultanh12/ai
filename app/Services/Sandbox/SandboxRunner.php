<?php

namespace App\Services\Sandbox;

use App\Models\Project;
use Exception;
use Symfony\Component\Process\Process;

class SandboxRunner
{
    protected WorkspaceManager $workspaceManager;

    public function __construct(WorkspaceManager $workspaceManager)
    {
        $this->workspaceManager = $workspaceManager;
    }

    /**
     * Run an isolated command within the project workspace using structured argv.
     * Prevents shell injection, strips all host/app credentials, and limits time and output size.
     */
    public function runCommand(Project $project, array $command, int $timeoutSeconds = 30): array
    {
        $workspaceDir = $this->workspaceManager->getWorkspacePath($project->id);

        if (!is_dir($workspaceDir)) {
            throw new Exception("Direktori workspace tidak ditemukan.");
        }

        // Validate command is array of arguments (no raw string shell interpolation)
        foreach ($command as $arg) {
            if (!is_string($arg) || str_contains($arg, "\0")) {
                throw new Exception("Argumen perintah tidak valid.");
            }
        }

        // Sanitized environment: strip all sensitive secrets
        $sanitizedEnv = [
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'NODE_ENV' => 'production',
            'CI' => 'true',
            'HOME' => $workspaceDir,
            'TMP' => sys_get_temp_dir(),
            'TEMP' => sys_get_temp_dir(),
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\Windows',
        ];

        $process = new Process($command, $workspaceDir, $sanitizedEnv, null, $timeoutSeconds);

        $start = microtime(true);
        $outputBuffer = '';
        $maxOutputBytes = 256 * 1024; // 256 KB max log length

        try {
            $process->run(function ($type, $buffer) use (&$outputBuffer, $maxOutputBytes) {
                if (strlen($outputBuffer) < $maxOutputBytes) {
                    $outputBuffer .= $buffer;
                }
            });

            $duration = (int) round((microtime(true) - $start) * 1000);
            $exitCode = $process->getExitCode() ?? 1;

            return [
                'success' => $process->isSuccessful(),
                'exit_code' => $exitCode,
                'output' => trim($outputBuffer),
                'duration_ms' => $duration,
            ];
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'exit_code' => 124,
                'output' => "Proses melebihi batas waktu eksekusi ({$timeoutSeconds} detik): " . $outputBuffer,
                'duration_ms' => $duration,
            ];
        } catch (Exception $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'exit_code' => 1,
                'output' => "Kesalahan eksekusi: " . $e->getMessage(),
                'duration_ms' => $duration,
            ];
        }
    }

    /**
     * Run static checks and build verification on workspace files.
     */
    public function verifyProject(Project $project): array
    {
        $files = $project->files()->get();
        $errors = [];
        $logs = [];

        $logs[] = "[Pemeriksaan Sandbox] Memeriksa " . $files->count() . " berkas proyek...";

        foreach ($files as $file) {
            // Check PHP files syntax
            if (str_ends_with($file->path, '.php')) {
                $phpCheck = $this->checkPhpSyntax($file->content);
                if (!$phpCheck['valid']) {
                    $errors[] = "Sintaks PHP tidak valid pada {$file->path}: " . $phpCheck['error'];
                    $logs[] = "✗ {$file->path}: " . $phpCheck['error'];
                } else {
                    $logs[] = "✓ {$file->path}: Sintaks PHP valid";
                }
            }

            // Check JSON files syntax
            if (str_ends_with($file->path, '.json')) {
                json_decode($file->content);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "JSON tidak valid pada {$file->path}: " . json_last_error_msg();
                    $logs[] = "✗ {$file->path}: " . json_last_error_msg();
                } else {
                    $logs[] = "✓ {$file->path}: Format JSON valid";
                }
            }
        }

        $isPassed = empty($errors);
        $logs[] = $isPassed ? "✓ Semua pemeriksaan kode sandbox berhasil lolos." : "✗ Terdapat " . count($errors) . " kesalahan pada proyek.";

        return [
            'success' => $isPassed,
            'errors' => $errors,
            'log' => implode("\n", $logs),
        ];
    }

    /**
     * Safe PHP syntax check in-memory.
     */
    protected function checkPhpSyntax(?string $code): array
    {
        if (empty($code)) {
            return ['valid' => true, 'error' => null];
        }

        // Tokenize check with parse validation
        try {
            $tokens = @token_get_all($code, TOKEN_PARSE);
            return ['valid' => true, 'error' => null];
        } catch (\ParseError $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
