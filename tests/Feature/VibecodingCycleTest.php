<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\Sandbox\SandboxRunner;
use App\Services\Sandbox\WorkspaceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VibecodingCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_initialization_and_atomic_checkpoints(): void
    {
        $user = User::factory()->create();
        $workspace = app(WorkspaceManager::class);

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Cycle App',
            'slug' => 'cycle-app',
        ]);

        // 1. Initialize starter template
        $workspace->initializeStarterTemplate($project);

        $this->assertDatabaseHas('project_files', [
            'project_id' => $project->id,
            'path' => 'resources/js/App.vue',
        ]);
        $this->assertDatabaseHas('project_files', [
            'project_id' => $project->id,
            'path' => 'package.json',
        ]);

        $initialContent = $workspace->getFile($project, 'resources/js/App.vue');
        $this->assertStringContainsString('Vibecoding XZY', $initialContent);

        // 2. Create checkpoint
        $checkpoint = $workspace->createCheckpoint($project, $user->id, 'Initial working version');
        $this->assertNotNull($checkpoint->id);
        $this->assertGreaterThan(0, $checkpoint->file_count);

        // 3. Modify file (simulating agent patch)
        $modifiedContent = "<template><div>New Vibecoded Component</div></template>";
        $workspace->putFile($project, 'resources/js/App.vue', $modifiedContent);
        $this->assertEquals($modifiedContent, $workspace->getFile($project, 'resources/js/App.vue'));

        // 4. Restore from checkpoint
        $workspace->restoreCheckpoint($checkpoint);
        $this->assertEquals($initialContent, $workspace->getFile($project, 'resources/js/App.vue'));
    }

    public function test_sandbox_runner_detects_syntax_errors(): void
    {
        $user = User::factory()->create();
        $workspace = app(WorkspaceManager::class);
        $sandbox = app(SandboxRunner::class);

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Broken App',
            'slug' => 'broken-app',
        ]);

        // Add broken PHP file
        $workspace->putFile($project, 'routes/web.php', '<?php function broken( {');

        $check = $sandbox->verifyProject($project);
        $this->assertFalse($check['success']);
        $this->assertNotEmpty($check['errors']);

        // Fix the file
        $workspace->putFile($project, 'routes/web.php', '<?php function valid() { return true; }');

        $checkFixed = $sandbox->verifyProject($project);
        $this->assertTrue($checkFixed['success']);
    }
}
