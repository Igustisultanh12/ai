<?php

namespace Tests\Feature;

use App\Models\AgentConfig;
use App\Models\Conversation;
use App\Models\GitHubConnection;
use App\Models\Message;
use App\Models\ModelMultiplier;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\PushCommandParser;
use App\Services\Sandbox\WorkspaceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushCommandAndCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function connectedUser(): User
    {
        $user = User::factory()->create();
        GitHubConnection::create([
            'user_id' => $user->id,
            'github_username' => 'budi',
            'token_encrypted' => 'ghp_testtoken',
            'status' => 'connected',
        ]);
        return $user;
    }

    protected function projectWithFiles(User $user, ?string $repo = 'budi/lama'): Project
    {
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Project A',
            'slug' => 'project-a',
            'github_repo' => $repo,
            'github_branch' => 'main',
        ]);
        $ws = app(WorkspaceManager::class);
        $ws->putFile($project, 'index.html', '<h1>A</h1>');
        $ws->putFile($project, 'src/app.js', 'console.log("a")');
        return $project;
    }

    /** GitHub API for a brand-new, empty repository budi/project-a. */
    protected function fakeEmptyRepo(): void
    {
        Http::fake([
            'api.github.com/repos/budi/project-a/git/ref/heads/*' => Http::response(['message' => 'Git Repository is empty.'], 409),
            'api.github.com/repos/budi/project-a/contents/*' => Http::response([
                'commit' => ['sha' => 'init1', 'tree' => ['sha' => 'inittree']],
            ], 201),
            'api.github.com/repos/budi/project-a/git/blobs' => Http::response(['sha' => 'blob1'], 201),
            'api.github.com/repos/budi/project-a/git/trees' => Http::response(['sha' => 'tree2'], 201),
            'api.github.com/repos/budi/project-a/git/commits' => Http::response(['sha' => 'commit2'], 201),
            'api.github.com/repos/budi/project-a/git/refs/heads/main' => Http::response(['object' => ['sha' => 'commit2']]),
            'api.github.com/repos/budi/project-a' => Http::response(['default_branch' => 'main', 'size' => 0, 'permissions' => ['push' => true]]),
        ]);
    }

    public function test_parser_understands_free_text_push_commands(): void
    {
        $p = new PushCommandParser();

        $this->assertSame('budi/project-a', $p->parse('push ke https://github.com/budi/project-a')['repo']);
        $this->assertTrue($p->parse('tolong push project ini ke repo baru aku https://github.com/budi/project-a.git ya')['push_only']);
        $this->assertSame('dev', $p->parse('push ke github.com/budi/project-a branch dev')['branch']);
        $this->assertSame('fitur/login', $p->parse('upload ke https://github.com/budi/project-a/tree/fitur/login')['branch']);
        $this->assertSame('init project', $p->parse('commit ke git@github.com:budi/a.git dengan pesan "init project"')['commit_message']);
        $this->assertFalse($p->parse('buatkan halaman login lalu push ke https://github.com/budi/project-a')['push_only']);
        $this->assertNull($p->parse('lihat https://github.com/budi/project-a deh'));
        $this->assertNull($p->parse('push perubahan ini'));
    }

    public function test_workspace_prompt_pushes_project_to_new_empty_repo_and_remembers_it(): void
    {
        $user = $this->connectedUser();
        $project = $this->projectWithFiles($user, 'budi/lama');
        $this->fakeEmptyRepo();

        $res = $this->actingAs($user)->postJson("/api/projects/{$project->id}/vibecoding/run", [
            'prompt' => 'push ke repo yang baru aku buat https://github.com/budi/project-a',
        ]);

        $res->assertOk()
            ->assertJsonPath('push.repo', 'budi/project-a')
            ->assertJsonPath('push.branch', 'main')
            ->assertJsonPath('run.type', 'github_push')
            ->assertJsonPath('run.status', 'completed');

        $this->assertEqualsCanonicalizing(['index.html', 'src/app.js'], $res->json('push.files'));

        $project->refresh();
        $this->assertSame('budi/project-a', $project->github_repo);

        // Nothing was sent to the old repo
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'budi/lama'));
        // First file went through the Contents API because the repo was empty
        Http::assertSent(fn ($req) => $req->method() === 'PUT' && str_contains($req->url(), '/contents/'));
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/git/commits') && $req['parents'] === ['init1']);
    }

    public function test_chat_command_pushes_linked_project(): void
    {
        $user = $this->connectedUser();
        $project = $this->projectWithFiles($user, null);
        $conv = Conversation::create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'Percakapan Baru', 'model' => 'glm-5.1']);
        $this->fakeEmptyRepo();

        $res = $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", [
            'content' => 'push ke https://github.com/budi/project-a',
        ], ['Accept' => 'text/event-stream']);

        $res->assertOk();
        $body = $res->streamedContent();
        $this->assertStringContainsString('Berhasil push', $body);
        $this->assertStringContainsString('budi\/project-a', $body);

        $reply = Message::where('conversation_id', $conv->id)->where('role', 'assistant')->first();
        $this->assertStringContainsString('proyek **Project A**', $reply->content);
        $this->assertSame('budi/project-a', $project->fresh()->github_repo);
    }

    public function test_chat_command_without_project_pushes_files_written_in_chat(): void
    {
        $user = $this->connectedUser();
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);
        Message::create(['conversation_id' => $conv->id, 'role' => 'assistant', 'status' => 'completed',
            'content' => "Ini dia:\n```html:index.html\n<h1>v1</h1>\n```\n"]);
        Message::create(['conversation_id' => $conv->id, 'role' => 'assistant', 'status' => 'completed',
            'content' => "Revisi:\n```html:index.html\n<h1>v2</h1>\n```\n```bash\nnpm i\n```"]);
        $this->fakeEmptyRepo();

        $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", [
            'content' => 'tolong push ke https://github.com/budi/project-a',
        ])->assertOk();

        Http::assertSent(fn ($req) => $req->method() === 'PUT'
            && str_ends_with($req->url(), '/contents/index.html')
            && base64_decode($req['content']) === "<h1>v2</h1>");
    }

    public function test_catalog_lists_every_live_model_and_syncs_multipliers(): void
    {
        AgentConfig::create([
            'base_url' => 'https://api.tokenomixai.com/v1',
            'api_key_encrypted' => 'sk-tkx-testkey',
            'default_model' => 'glm-5.1',
            'allowed_models' => ['glm-5.1'], // stale admin allow-list must not hide models
            'is_active' => true,
        ]);
        ModelMultiplier::create(['model_id' => 'glm-5.1', 'multiplier' => 2]);
        ModelMultiplier::create(['model_id' => 'gpt-6-astra', 'multiplier' => 42]);
        ModelMultiplier::create(['model_id' => 'kimi-k3', 'multiplier' => 9, 'source' => 'Admin Manual Override']);

        Http::fake([
            'api.tokenomixai.com/v1/models' => Http::response(['data' => [
                ['id' => 'glm-5.1', 'multiplier' => 2],
                ['id' => 'claude-opus-5-5', 'multiplier' => '32x'],
                ['id' => 'kimi-k3', 'multiplier' => 3],
            ]]),
        ]);

        $user = User::factory()->create();
        $models = collect($this->actingAs($user)->getJson('/api/models')->assertOk()->json('models'))->keyBy('id');

        $this->assertTrue($models['claude-opus-5-5']['available']);
        $this->assertEquals(32, $models['claude-opus-5-5']['multiplier']);
        $this->assertFalse($models['gpt-6-astra']['available']); // known locally, not in stock
        $this->assertEquals(9, $models['kimi-k3']['multiplier']); // admin override kept

        $this->assertEquals(32.0, (float) ModelMultiplier::where('model_id', 'claude-opus-5-5')->value('multiplier'));

        $conv = $this->actingAs($user)->postJson('/api/conversations', ['model' => 'claude-opus-5-5'])->assertCreated();
        $this->assertSame('claude-opus-5-5', $conv->json('conversation.model'));
        $this->actingAs($user)->postJson('/api/conversations', ['model' => 'gpt-6-astra'])->assertStatus(422);
    }
}
