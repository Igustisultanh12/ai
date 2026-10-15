<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Sandbox\PreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreviewController extends Controller
{
    protected PreviewService $previewService;

    public function __construct(PreviewService $previewService)
    {
        $this->previewService = $previewService;
    }

    /**
     * Generate an ephemeral preview token for the authorized project owner.
     */
    public function generateToken(Request $request, string $projectId): JsonResponse
    {
        $user = $request->user();
        $project = Project::where('id', $projectId)->where('user_id', $user->id)->firstOrFail();

        $token = $this->previewService->generatePreviewToken($project, 60);

        return response()->json([
            'token' => $token,
            'preview_url' => url("/preview/{$project->id}/{$token}"),
            'expires_in_minutes' => 60,
        ]);
    }

    /**
     * Render the sandboxed project preview without platform authentication cookies.
     */
    public function render(Request $request, string $projectId, string $token): Response
    {
        if (!$this->previewService->validatePreviewToken($projectId, $token)) {
            return response("<div style='font-family:sans-serif;color:#ef4444;padding:24px;background:#0f172a;'>Sesi token pratinjau tidak valid atau telah kedaluwarsa. Harap muat ulang ruang kerja XZY.</div>", 403);
        }

        $project = Project::findOrFail($projectId);
        $html = $this->previewService->renderPreviewHtml($project);

        $response = response($html);
        // Sandboxed headers to protect host platform from untrusted code
        $response->headers->set('Content-Security-Policy', "default-src 'self' 'unsafe-inline' https: data:; connect-src 'self' https:; font-src 'self' https: data:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; script-src 'self' 'unsafe-inline' https:;");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
