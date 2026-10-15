<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\ProjectExport;
use App\Models\User;
use App\Services\Sandbox\WorkspaceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_another_users_project(): void
    {
        $userA = User::factory()->create(['role' => 'user']);
        $userB = User::factory()->create(['role' => 'user']);

        $workspaceManager = app(WorkspaceManager::class);

        $projectB = Project::create([
            'user_id' => $userB->id,
            'name' => 'Project B',
            'slug' => 'project-b',
            'template' => 'laravel-vue3',
        ]);
        $workspaceManager->initializeStarterTemplate($projectB);

        // User A tries to view Project B
        $response = $this->actingAs($userA)->getJson("/api/projects/{$projectB->id}");
        $response->assertStatus(403);

        // User A tries to get files from Project B
        $filesRes = $this->actingAs($userA)->getJson("/api/projects/{$projectB->id}/files");
        $filesRes->assertStatus(403);

        // User A tries to delete Project B
        $delRes = $this->actingAs($userA)->deleteJson("/api/projects/{$projectB->id}");
        $delRes->assertStatus(403);
    }

    public function test_user_cannot_access_another_users_conversation(): void
    {
        $userA = User::factory()->create(['role' => 'user']);
        $userB = User::factory()->create(['role' => 'user']);

        $convB = Conversation::create([
            'user_id' => $userB->id,
            'title' => 'Secret Conversation B',
            'model' => 'glm-5.1',
        ]);

        $response = $this->actingAs($userA)->getJson("/api/conversations/{$convB->id}");
        $response->assertStatus(403);
    }

    public function test_user_cannot_download_another_users_export(): void
    {
        $userA = User::factory()->create(['role' => 'user']);
        $userB = User::factory()->create(['role' => 'user']);

        $projectB = Project::create([
            'user_id' => $userB->id,
            'name' => 'Project B',
            'slug' => 'project-b',
        ]);

        $exportB = ProjectExport::create([
            'project_id' => $projectB->id,
            'user_id' => $userB->id,
            'format' => 'zip',
            'status' => 'ready',
            'file_path' => tempnam(sys_get_temp_dir(), 'exp'),
        ]);

        $response = $this->actingAs($userA)->getJson("/api/exports/{$exportB->id}/download");
        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $dashRes = $this->actingAs($user)->getJson('/api/admin/dashboard');
        $dashRes->assertStatus(403);

        $agentRes = $this->actingAs($user)->getJson('/api/admin/agent');
        $agentRes->assertStatus(403);

        $usersRes = $this->actingAs($user)->getJson('/api/admin/users');
        $usersRes->assertStatus(403);
    }

    public function test_cannot_demote_or_suspend_the_last_active_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Attempt to demote self (when no other admin exists)
        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$admin->id}", [
            'role' => 'user',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tindakan ditolak: Anda tidak dapat mengubah peran atau menonaktifkan administrator aktif terakhir.');

        // Attempt to suspend self
        $suspendRes = $this->actingAs($admin)->putJson("/api/admin/users/{$admin->id}", [
            'status' => 'suspended',
        ]);

        $suspendRes->assertStatus(422);
    }
}
