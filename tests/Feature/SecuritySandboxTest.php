<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\GitHubService;
use App\Services\Sandbox\PreviewService;
use App\Services\Sandbox\WorkspaceManager;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_manager_rejects_path_traversal(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Sandbox Sec Test',
            'slug' => 'sandbox-sec-test',
        ]);

        $workspace = app(WorkspaceManager::class);

        // Test ../ traversal
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Path traversal terdeteksi');
        $workspace->validateSafePath($project->id, '../../etc/passwd');
    }

    public function test_workspace_manager_rejects_null_byte_injection(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Sandbox Sec Test 2',
            'slug' => 'sandbox-sec-test-2',
        ]);

        $workspace = app(WorkspaceManager::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('null byte');
        $workspace->validateSafePath($project->id, "App.vue\0.php");
    }

    public function test_workspace_manager_rejects_absolute_paths(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Sandbox Sec Test 3',
            'slug' => 'sandbox-sec-test-3',
        ]);

        $workspace = app(WorkspaceManager::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Absolute path tidak diizinkan');
        $workspace->validateSafePath($project->id, 'C:/Windows/System32/cmd.exe');
    }

    public function test_preview_serves_with_strict_csp_and_frame_options(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Preview CSP Test',
            'slug' => 'preview-csp-test',
        ]);

        $previewService = app(PreviewService::class);
        $token = $previewService->generatePreviewToken($project, 60);

        $response = $this->get("/preview/{$project->id}/{$token}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_admin_agent_config_rejects_ssrf_hosts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // Attempt SSRF to localhost
        $resLocalhost = $this->putJson('/api/admin/agent', [
            'name' => 'Malicious',
            'base_url' => 'http://127.0.0.1:8080/v1',
            'default_model' => 'glm-5.1',
            'allowed_models' => ['glm-5.1'],
            'max_output_tokens' => 4096,
            'temperature' => 0.7,
            'timeout_seconds' => 60,
            'concurrency_limit' => 5,
            'max_iterations' => 5,
            'is_active' => true,
        ]);
        $resLocalhost->assertStatus(422)
            ->assertJsonPath('message', 'Alamat URL host tidak diizinkan untuk alasan keamanan SSRF.');

        // Attempt SSRF to AWS/GCP cloud metadata
        $resMetadata = $this->putJson('/api/admin/agent', [
            'name' => 'Metadata Stealer',
            'base_url' => 'http://169.254.169.254/latest/meta-data',
            'default_model' => 'glm-5.1',
            'allowed_models' => ['glm-5.1'],
            'max_output_tokens' => 4096,
            'temperature' => 0.7,
            'timeout_seconds' => 60,
            'concurrency_limit' => 5,
            'max_iterations' => 5,
            'is_active' => true,
        ]);
        $resMetadata->assertStatus(422);
    }

    public function test_github_service_secret_scanner_detects_credentials(): void
    {
        $githubService = app(GitHubService::class);

        // 1. Clean files test
        $cleanFiles = [
            'resources/js/App.vue' => '<template><div>Hello</div></template>',
            'package.json' => '{"name": "test"}',
            '.env.example' => 'APP_NAME=Test',
        ];
        $cleanScan = $githubService->scanForSecrets($cleanFiles);
        $this->assertFalse($cleanScan['has_secrets']);

        // 2. Sensitive .env file test
        $dirtyFiles1 = [
            '.env' => 'DB_PASSWORD=SuperSecretDatabasePassword123!',
        ];
        $dirtyScan1 = $githubService->scanForSecrets($dirtyFiles1);
        $this->assertTrue($dirtyScan1['has_secrets']);

        // 3. AWS Key test
        $dirtyFiles2 = [
            'config.php' => 'const AWS_KEY = "AKIAIOSFODNN7EXAMPLE";',
        ];
        $dirtyScan2 = $githubService->scanForSecrets($dirtyFiles2);
        $this->assertTrue($dirtyScan2['has_secrets']);

        // 4. GitHub PAT test
        $dirtyFiles3 = [
            'token.txt' => 'ghp_1234567890abcdefghijklmnopqrstuvwxyz',
        ];
        $dirtyScan3 = $githubService->scanForSecrets($dirtyFiles3);
        $this->assertTrue($dirtyScan3['has_secrets']);
    }
}
