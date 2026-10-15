<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectExport;
use App\Services\Auth\AuditService;
use App\Services\Exports\RarExportService;
use App\Services\Exports\ZipExportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    protected ZipExportService $zipService;
    protected RarExportService $rarService;
    protected AuditService $auditService;

    public function __construct(
        ZipExportService $zipService,
        RarExportService $rarService,
        AuditService $auditService
    ) {
        $this->zipService = $zipService;
        $this->rarService = $rarService;
        $this->auditService = $auditService;
    }

    /**
     * Get readiness status for export formats (ZIP & RAR).
     */
    public function readiness(): JsonResponse
    {
        $rarStatus = $this->rarService->getReadinessStatus();
        $zipStatus = [
            'is_ready' => class_exists('ZipArchive'),
            'status' => class_exists('ZipArchive') ? 'ready' : 'not_available',
            'message' => class_exists('ZipArchive') ? 'Ekstensi ZIP siap digunakan.' : 'Ekstensi PHP ZipArchive tidak terpasang.',
        ];

        return response()->json([
            'zip' => $zipStatus,
            'rar' => $rarStatus,
        ]);
    }

    /**
     * Create an export (ZIP or RAR).
     */
    public function createExport(Request $request, string $projectId): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $projectId);
        $user = $request->user();

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:zip,rar'],
        ]);

        $format = $validated['format'];

        // If RAR requested, verify readiness
        if ($format === 'rar') {
            $readiness = $this->rarService->getReadinessStatus();
            if (!$readiness['is_ready']) {
                return response()->json([
                    'message' => 'Format ekspor RAR belum siap pada server ini: ' . $readiness['message'],
                    'instructions' => $readiness['instructions'],
                ], 422);
            }
        }

        $export = ProjectExport::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'format' => $format,
            'status' => 'processing',
        ]);

        try {
            if ($format === 'zip') {
                $export = $this->zipService->export($project, $export);
            } else {
                $export = $this->rarService->export($project, $export);
            }

            $this->auditService->log("export.created_{$format}", $user, 'ProjectExport', (string) $export->id, [
                'project_id' => $project->id,
                'format' => $format,
                'size_bytes' => $export->file_size_bytes,
                'checksum' => $export->checksum_sha256,
            ]);

            return response()->json([
                'message' => "Arsip {$format} berhasil dibuat.",
                'export' => $export,
            ]);
        } catch (Exception $e) {
            $export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Gagal membuat arsip {$format}: " . $e->getMessage(),
                'export' => $export,
            ], 500);
        }
    }

    /**
     * List exports for a project.
     */
    public function list(Request $request, string $projectId): JsonResponse
    {
        $project = $this->getAuthorizedProject($request, $projectId);

        $exports = ProjectExport::where('project_id', $project->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['exports' => $exports]);
    }

    /**
     * Download generated export archive with ownership check.
     */
    public function download(Request $request, string $exportId): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $export = ProjectExport::where('id', $exportId)->firstOrFail();

        // Ownership authorization check
        if ($export->user_id !== $user->id) {
            abort(403, 'Akses unduhan ditolak.');
        }

        if ($export->status !== 'ready' || !file_exists($export->file_path)) {
            return response()->json(['message' => 'Berkas arsip tidak tersedia atau proses belum selesai.'], 404);
        }

        $project = $export->project;
        $filename = "{$project->slug}-export.{$export->format}";

        $this->auditService->log('export.downloaded', $user, 'ProjectExport', (string) $export->id, [
            'format' => $export->format,
            'filename' => $filename,
        ]);

        return response()->download($export->file_path, $filename, [
            'Content-Type' => $export->format === 'zip' ? 'application/zip' : 'application/x-rar-compressed',
            'X-Checksum-SHA256' => $export->checksum_sha256,
        ]);
    }

    protected function getAuthorizedProject(Request $request, string $id): Project
    {
        $user = $request->user();
        $project = Project::where('id', $id)->firstOrFail();

        if ($project->user_id !== $user->id) {
            abort(403, 'Akses proyek ditolak.');
        }

        return $project;
    }
}
