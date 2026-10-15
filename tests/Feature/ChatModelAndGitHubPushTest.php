<?php

namespace Tests\Feature;

use App\Models\AgentConfig;
use App\Models\Conversation;
use App\Models\GitHubConnection;
use App\Models\ModelMultiplier;
use App\Models\User;
use App\Services\GitHub\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatModelAndGitHubPushTest extends TestCase
{
    use RefreshDatabase;

    protected function configureGateway(): void
    {
        AgentConfig::create([
            'name' => 'TokenomixAI Primary',
            'base_url' => 'https://api.tokenomixai.com/v1',
            'api_key_encrypted' => 'sk-tkx-testkey',
            'default_model' => 'glm-5.1',
            'is_active' => true,
        ]);
    }

    public function test_models_endpoint_uses_live_tokenomix_catalog(): void
    {
        $this->configureGateway();
        ModelMultiplier::create(['model_id' => 'kimi-k3', 'multiplier' => 2.5]);

        Http::fake([
            'api.tokenomixai.com/v1/models' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'glm-5.1', 'object' => 'model'],
                    ['id' => 'kimi-k3', 'object' => 'model'],
                    ['id' => 'deepseek-v4-flash-0731', 'object' => 'model'],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $res = $this->actingAs($user)->getJson('/api/models')->assertOk();

        $this->assertSame('live', $res->json('source'));
        $this->assertSame('glm-5.1', $res->json('default'));
        $this->assertSame(['glm-5.1', 'kimi-k3', 'deepseek-v4-flash-0731'], array_column($res->json('models'), 'id'));
        $this->assertEquals(2.5, $res->json('models.1.multiplier'));

        Http::assertSent(fn ($req) => $req->url() === 'https://api.tokenomixai.com/v1/models'
            && $req->hasHeader('Authorization', 'Bearer sk-tkx-testkey'));
    }

    public function test_models_endpoint_falls_back_to_local_catalog_without_api_key(): void
    {
        ModelMultiplier::create(['model_id' => 'glm-5.2', 'multiplier' => 2]);
        ModelMultiplier::create(['model_id' => 'auto', 'multiplier' => 1]);

        $user = User::factory()->create();
        $res = $this->actingAs($user)->getJson('/api/models')->assertOk();

        $this->assertSame('local', $res->json('source'));
        $this->assertSame(['glm-5.2'], array_column($res->json('models'), 'id'));
    }

    public function test_conversation_model_can_be_changed_and_is_validated(): void
    {
        $this->configureGateway();
        Http::fake([
            'api.tokenomixai.com/v1/models' => Http::response(['data' => [['id' => 'glm-5.1'], ['id' => 'kimi-k3']]]),
        ]);

        $user = User::factory()->create();
        $conv = $this->actingAs($user)->postJson('/api/conversations', ['model' => 'kimi-k3'])
            ->assertCreated()
            ->json('conversation');
        $this->assertSame('kimi-k3', $conv['model']);

        $this->actingAs($user)->putJson("/api/conversations/{$conv['id']}", ['model' => 'glm-5.1'])
            ->assertOk()
            ->assertJsonPath('conversation.model', 'glm-5.1');

        $this->actingAs($user)->putJson("/api/conversations/{$conv['id']}", ['model' => 'not-a-model'])
            ->assertStatus(422);

        $this->assertSame('glm-5.1', Conversation::find($conv['id'])->model);
    }

    public function test_chat_files_are_pushed_to_github_in_one_commit(): void
    {
        $user = User::factory()->create();
        GitHubConnection::create([
            'user_id' => $user->id,
            'github_username' => 'octo',
            'token_encrypted' => 'ghp_testtoken',
            'scopes' => ['repo'],
            'status' => 'connected',
        ]);
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'Bikin API', 'model' => 'glm-5.1']);

        Http::fake([
            'api.github.com/repos/octo/app/git/ref/heads/main' => Http::response(['object' => ['sha' => 'parent123']]),
            'api.github.com/repos/octo/app/git/commits/parent123' => Http::response(['tree' => ['sha' => 'tree123']]),
            'api.github.com/repos/octo/app/git/blobs' => Http::response(['sha' => 'blob123'], 201),
            'api.github.com/repos/octo/app/git/trees' => Http::response(['sha' => 'newtree'], 201),
            'api.github.com/repos/octo/app/git/commits' => Http::response(['sha' => 'newcommit'], 201),
            'api.github.com/repos/octo/app/git/refs/heads/main' => Http::response(['object' => ['sha' => 'newcommit']]),
        ]);

        $res = $this->actingAs($user)->postJson("/api/conversations/{$conv->id}/github/push", [
            'repo' => 'octo/app',
            'branch' => 'main',
            'commit_message' => 'feat: tambah controller',
            'files' => [
                ['path' => 'app/Http/Controllers/UserController.php', 'content' => "<?php\n// user"],
                ['path' => './routes/api.php', 'content' => "<?php\n"],
            ],
        ]);

        $res->assertOk()
            ->assertJsonPath('commit_sha', 'newcommit')
            ->assertJsonPath('branch', 'main')
            ->assertJsonPath('branch_created', false)
            ->assertJsonPath('files', ['app/Http/Controllers/UserController.php', 'routes/api.php']);

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/git/trees')
            && $req['base_tree'] === 'tree123'
            && count($req['tree']) === 2);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/git/commits')
            && $req->method() === 'POST'
            && $req['parents'] === ['parent123']
            && $req['message'] === 'feat: tambah controller');
        Http::assertSent(fn ($req) => $req->method() === 'PATCH' && $req['force'] === false);
    }

    public function test_new_branch_is_created_from_default_branch(): void
    {
        $user = User::factory()->create();
        GitHubConnection::create([
            'user_id' => $user->id,
            'github_username' => 'octo',
            'token_encrypted' => 'ghp_testtoken',
            'status' => 'connected',
        ]);
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);

        Http::fake([
            'api.github.com/repos/octo/app/git/ref/heads/fitur/chat' => Http::response(['message' => 'Not Found'], 404),
            'api.github.com/repos/octo/app/git/ref/heads/main' => Http::response(['object' => ['sha' => 'mainsha']]),
            'api.github.com/repos/octo/app/git/commits/mainsha' => Http::response(['tree' => ['sha' => 'maintree']]),
            'api.github.com/repos/octo/app/git/blobs' => Http::response(['sha' => 'b1'], 201),
            'api.github.com/repos/octo/app/git/trees' => Http::response(['sha' => 't1'], 201),
            'api.github.com/repos/octo/app/git/commits' => Http::response(['sha' => 'c1'], 201),
            'api.github.com/repos/octo/app/git/refs' => Http::response(['ref' => 'refs/heads/fitur/chat'], 201),
            'api.github.com/repos/octo/app' => Http::response(['default_branch' => 'main']),
        ]);

        $this->actingAs($user)->postJson("/api/conversations/{$conv->id}/github/push", [
            'repo' => 'octo/app',
            'branch' => 'fitur/chat',
            'files' => [['path' => 'README.md', 'content' => '# hi']],
        ])->assertOk()->assertJsonPath('branch_created', true);

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/git/refs')
            && $req['ref'] === 'refs/heads/fitur/chat' && $req['sha'] === 'c1');
    }

    public function test_chat_push_rejects_unsafe_paths_secrets_and_foreign_conversations(): void
    {
        $user = User::factory()->create();
        GitHubConnection::create([
            'user_id' => $user->id,
            'github_username' => 'octo',
            'token_encrypted' => 'ghp_testtoken',
            'status' => 'connected',
        ]);
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);
        Http::fake();

        $this->actingAs($user)->postJson("/api/conversations/{$conv->id}/github/push", [
            'repo' => 'octo/app', 'branch' => 'main',
            'files' => [['path' => '../etc/passwd', 'content' => 'x']],
        ])->assertStatus(422);

        $this->actingAs($user)->postJson("/api/conversations/{$conv->id}/github/push", [
            'repo' => 'octo/app', 'branch' => 'main',
            'files' => [['path' => 'config.php', 'content' => 'AWS=AKIAABCDEFGHIJKLMNOP']],
        ])->assertStatus(422)->assertJsonFragment(['message' => "Push dibatalkan karena terdeteksi kredensial rahasia:\nPotensi kebocoran AWS Access Key terdeteksi pada berkas: config.php"]);

        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/api/conversations/{$conv->id}/github/push", [
            'repo' => 'octo/app', 'branch' => 'main',
            'files' => [['path' => 'a.txt', 'content' => 'x']],
        ])->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_path_sanitizer(): void
    {
        $gh = app(GitHubService::class);
        $this->assertSame('src/app.ts', $gh->sanitizePath('./src//app.ts'));
        $this->assertSame('src/app.ts', $gh->sanitizePath('src\\app.ts'));
        $this->assertNull($gh->sanitizePath('/etc/passwd'));
        $this->assertNull($gh->sanitizePath('a/../b'));
        $this->assertNull($gh->sanitizePath('.git/config'));
        $this->assertNull($gh->sanitizePath('C:/x.txt'));
    }
}
