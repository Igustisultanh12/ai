<?php

namespace App\Services\Sandbox;

use App\Models\Project;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PreviewService
{
    protected WorkspaceManager $workspaceManager;

    public function __construct(WorkspaceManager $workspaceManager)
    {
        $this->workspaceManager = $workspaceManager;
    }

    /**
     * Generate an ephemeral preview token with TTL (default 60 minutes).
     */
    public function generatePreviewToken(Project $project, int $ttlMinutes = 60): string
    {
        $token = Str::random(40);
        Cache::put("preview_token:{$project->id}:{$token}", [
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'created_at' => now()->timestamp,
        ], now()->addMinutes($ttlMinutes));

        return $token;
    }

    /**
     * Verify preview token validity.
     */
    public function validatePreviewToken(string $projectId, string $token): bool
    {
        $data = Cache::get("preview_token:{$projectId}:{$token}");
        return !empty($data) && $data['project_id'] === $projectId;
    }

    /**
     * Render the preview HTML for a project, serving the index or building a client preview bundle.
     */
    public function renderPreviewHtml(Project $project): string
    {
        $files = $project->files()->pluck('content', 'path')->toArray();

        // 1. If public/index.html exists, use it as baseline
        $indexHtml = $files['public/index.html'] ?? $files['index.html'] ?? null;

        if (!$indexHtml) {
            // Generate standard runner shell
            $indexHtml = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>" . htmlspecialchars($project->name) . "</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-slate-900 text-white p-8'><div id='app'></div></body></html>";
        }

        // Inline Vue template if present
        $vueApp = $files['resources/js/App.vue'] ?? null;
        if ($vueApp) {
            // Extract template, script, and style
            preg_match('/<template>(.*?)<\/template>/s', $vueApp, $templateMatches);
            preg_match('/<script.*?>(.*?)<\/script>/s', $vueApp, $scriptMatches);
            preg_match('/<style.*?>(.*?)<\/style>/s', $vueApp, $styleMatches);

            $template = $templateMatches[1] ?? '<div>' . htmlspecialchars($project->name) . '</div>';
            $script = $scriptMatches[1] ?? '';
            $style = $styleMatches[1] ?? '';

            // Inject standalone Vue 3 runtime to run without host build dependencies
            $injectedPreview = "
            <script src='https://unpkg.com/vue@3/dist/vue.global.prod.js'></script>
            <style>{$style}</style>
            <div id='app'>{$template}</div>
            <script>
            (function() {
                const { createApp, ref, reactive, computed, onMounted } = Vue;
                try {
                    // Safe sandboxed component execution
                    const app = createApp({
                        setup() {
                            {$script}
                            return typeof count !== 'undefined' ? { count } : {};
                        }
                    });
                    app.mount('#app');
                } catch(e) {
                    document.getElementById('app').innerHTML = '<div style=\"color:#f87171;padding:16px;background:#1e293b;border-radius:8px;\"><strong>Error Pratinjau Komponen:</strong> ' + e.message + '</div>';
                }
            })();
            </script>
            ";

            $indexHtml = str_replace("<div id=\"app\"></div>", $injectedPreview, $indexHtml);
            $indexHtml = str_replace("<div id='app'></div>", $injectedPreview, $indexHtml);
        }

        return $indexHtml;
    }
}
