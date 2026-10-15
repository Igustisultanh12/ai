<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GitHubConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemRepoPushTest extends TestCase
{
    use RefreshDatabase;

    protected function pushAs(User $user)
    {
        GitHubConnection::create([
            'user_id' => $user->id,
            'github_username' => 'Igustisultanh12',
            'token_encrypted' => 'ghp_testtoken',
            'status' => 'connected',
        ]);
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);

        return $this->actingAs($user)->postJson("/api/conversations/{$conv->id}/github/push", [
            'repo' => 'https://github.com/Igustisultanh12/ai.git',
            'branch' => 'main',
            'files' => [['path' => 'README.md', 'content' => '# XZY']],
        ]);
    }

    public function test_admin_can_push_to_the_xzy_system_repo(): void
    {
        Http::fake([
            'api.github.com/repos/Igustisultanh12/ai/git/ref/heads/main' => Http::response(['object' => ['sha' => 'p1']]),
            'api.github.com/repos/Igustisultanh12/ai/git/commits/p1' => Http::response(['tree' => ['sha' => 't1']]),
            'api.github.com/repos/Igustisultanh12/ai/git/blobs' => Http::response(['sha' => 'b1'], 201),
            'api.github.com/repos/Igustisultanh12/ai/git/trees' => Http::response(['sha' => 't2'], 201),
            'api.github.com/repos/Igustisultanh12/ai/git/commits' => Http::response(['sha' => 'c2'], 201),
            'api.github.com/repos/Igustisultanh12/ai/git/refs/heads/main' => Http::response(['object' => ['sha' => 'c2']]),
        ]);

        $this->pushAs(User::factory()->create(['role' => 'admin']))
            ->assertOk()
            ->assertJsonPath('repo', 'Igustisultanh12/ai')
            ->assertJsonPath('commit_sha', 'c2');
    }

    public function test_regular_user_cannot_push_to_the_xzy_system_repo(): void
    {
        Http::fake();

        $this->pushAs(User::factory()->create(['role' => 'user']))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Repositori sistem XZY hanya dapat menjadi target push oleh administrator. Harap pilih repositori GitHub pribadi Anda.']);

        Http::assertNothingSent();
    }
}
