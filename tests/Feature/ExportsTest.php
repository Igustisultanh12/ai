<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectExport;
use App\Models\User;
use App\Services\Exports\RarExportService;
use App\Services\Exports\ZipExportService;
use App\Services\Sandbox\WorkspaceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_zip_export_creates_valid_extractable_archive(): void
    {
        $user = User::factory()->create();
        $workspace = app(WorkspaceManager::class);

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Exportable Project',
            'slug' => 'exportable-project',
        ]);
        $workspace->initializeStarterTemplate($project);

        $export = ProjectExport::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'format' => 'zip',
            'status' => 'pending',
        ]);

        $zipService = app(ZipExportService::class);
        $result = $zipService->export($project, $export);

        $this->assertEquals('ready', $result->status);
        $this->assertFileExists($result->file_path);
        $this->assertNotEmpty($result->checksum_sha256);
        $this->assertGreaterThan(0, $result->file_size_bytes);

        // Verify it is genuinely readable by ZipArchive
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($result->file_path) === true);

        // Ensure key files are present in the archive
        $this->assertNotFalse($zip->locateName('resources/js/App.vue'));
        $this->assertNotFalse($zip->locateName('package.json'));
        $this->assertNotFalse($zip->locateName('composer.json'));
        $this->assertNotFalse($zip->locateName('.env.example'));

        // Ensure secrets are excluded
        $this->assertFalse($zip->locateName('.env'));
        $this->assertFalse($zip->locateName('.git'));

        $zip->close();
    }

    public function test_rar_readiness_status_reports_actionable_information(): void
    {
        $rarService = app(RarExportService::class);
        $readiness = $rarService->getReadinessStatus();

        $this->assertArrayHasKey('is_ready', $readiness);
        $this->assertArrayHasKey('status', $readiness);
        $this->assertArrayHasKey('message', $readiness);

        if (!$readiness['is_ready']) {
            $this->assertNotNull($readiness['instructions']);
            $this->assertStringContainsString('RAR', $readiness['instructions']);
        }
    }
}
