<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GitHubConnection;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Services\Agent\TokenomixClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Scripted stand-in for the TokenomixAI streaming client: each call returns the
 * next scripted turn (text and/or tool calls) and records the messages it got.
 */
class ScriptedTokenomixClient extends TokenomixClient
{
    public array $calls = [];

    public function __construct(public array $turns)
    {
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function streamChatCompletion(array $payload, callable $callback): array
    {
        $this->calls[] = $payload;
        $turn = array_shift($this->turns) ?? ['text' => ''];

        if (!empty($turn['text'])) {
            $callback($turn['text'], false, null);
        }
        $callback('', false, ['prompt_tokens' => 100, 'completion_tokens' => 20]);
        $callback('', true, null);

        $toolCalls = [];
        foreach ($turn['tools'] ?? [] as $i => [$name, $args]) {
            $toolCalls[] = ['id' => "call_{$i}", 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]];
        }

        return ['tool_calls' => $toolCalls, 'finish_reason' => $toolCalls ? 'tool_calls' : 'stop', 'aborted' => false];
    }
}

class ChatAgentToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
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

    protected function fakeRepo(): void
    {
        Http::fake([
            'api.github.com/repos/budi/web/git/trees/main*' => Http::response(['tree' => [
                ['path' => 'index.html', 'type' => 'blob', 'size' => 20],
                ['path' => 'logo.png', 'type' => 'blob', 'size' => 999],
                ['path' => 'css/style.css', 'type' => 'blob', 'size' => 10],
            ], 'truncated' => false]),
            'api.github.com/repos/budi/web/contents/index.html*' => Http::response('<h1>Lama</h1>'),
            'api.github.com/repos/budi/web/contents/css/style.css*' => Http::response('h1{}'),
            'api.github.com/repos/budi/web/git/ref/heads/main' => Http::response(['object' => ['sha' => 'p1']]),
            'api.github.com/repos/budi/web/git/commits/p1' => Http::response(['tree' => ['sha' => 't1']]),
            'api.github.com/repos/budi/web/git/blobs' => Http::response(['sha' => 'b1'], 201),
            'api.github.com/repos/budi/web/git/trees' => Http::response(['sha' => 't2'], 201),
            'api.github.com/repos/budi/web/git/commits' => Http::response(['sha' => 'c2abcdef'], 201),
            'api.github.com/repos/budi/web/git/refs/heads/main' => Http::response(['object' => ['sha' => 'c2abcdef']]),
            'api.github.com/repos/budi/web' => Http::response(['default_branch' => 'main', 'permissions' => ['push' => true]]),
        ]);
    }

    public function test_chat_agent_reads_repo_edits_and_pushes_with_tools(): void
    {
        $user = $this->user();
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'Percakapan Baru', 'model' => 'glm-5.1']);
        $this->fakeRepo();

        $client = new ScriptedTokenomixClient([
            ['text' => 'Saya cek repo dulu.', 'tools' => [['github_list_files', ['repo' => 'https://github.com/budi/web']]]],
            ['tools' => [['github_read_files', ['repo' => 'budi/web', 'paths' => ['index.html']]]]],
            ['tools' => [['github_push_files', [
                'repo' => 'budi/web',
                'commit_message' => 'feat: animasi scroll',
                'files' => [['path' => 'index.html', 'content' => '<h1 class="fade">Baru</h1>']],
            ]]]],
            ['text' => 'Selesai, sudah di-push ke budi/web.'],
        ]);
        $this->app->instance(TokenomixClient::class, $client);

        $res = $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", [
            'content' => 'perbaiki https://github.com/budi/web tambahin animasi scroll terus push',
        ]);
        $res->assertOk();
        $res->streamedContent();

        // Tools were offered and results were fed back to the model
        $this->assertNotEmpty($client->calls[0]['tools']);
        $this->assertCount(4, $client->calls);
        $toolMsgs = array_values(array_filter($client->calls[2]['messages'], fn ($m) => $m['role'] === 'tool'));
        $this->assertStringContainsString('<h1>Lama</h1>', $toolMsgs[1]['content']);
        $this->assertStringContainsString('GitHub: terhubung sebagai @budi', $client->calls[0]['messages'][0]['content']);

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/git/commits')
            && $req->method() === 'POST' && $req['message'] === 'feat: animasi scroll');
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/git/blobs')
            && base64_decode($req['content']) === "<h1 class=\"fade\">Baru</h1>");

        $reply = Message::where('conversation_id', $conv->id)->where('role', 'assistant')->first();
        $this->assertSame('completed', $reply->status);
        $this->assertStringContainsString('<details>', $reply->content);
        $this->assertStringContainsString('https://github.com/budi/web/commit/c2abcdef', $reply->content);
        $this->assertStringContainsString('Selesai, sudah di-push', $reply->content);
    }

    public function test_tool_errors_are_reported_back_to_the_model(): void
    {
        $user = User::factory()->create(); // no GitHub connection
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);
        Http::fake();

        $client = new ScriptedTokenomixClient([
            ['tools' => [['github_list_files', ['repo' => 'budi/web']]]],
            ['text' => 'GitHub belum terhubung.'],
        ]);
        $this->app->instance(TokenomixClient::class, $client);

        $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", ['content' => 'lihat repo budi/web'])
            ->assertOk()->streamedContent();

        $toolMsg = collect($client->calls[1]['messages'])->firstWhere('role', 'tool');
        $this->assertStringContainsString('belum terhubung ke GitHub', $toolMsg['content']);
        $this->assertStringContainsString('GitHub: BELUM terhubung', $client->calls[0]['messages'][0]['content']);
        Http::assertNothingSent();
    }

    public function test_clone_repo_into_project_then_edit_and_push_project(): void
    {
        $user = $this->user();
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);
        $this->fakeRepo();

        $client = new ScriptedTokenomixClient([
            ['tools' => [['clone_repo_to_project', ['repo' => 'budi/web', 'project_name' => 'Web Budi']]]],
            ['tools' => [['project_write_files', ['files' => [['path' => 'index.html', 'content' => '<h1>Edit</h1>']]]]]],
            ['tools' => [['project_push_to_github', ['commit_message' => 'update']]]],
            ['text' => 'Beres.'],
        ]);
        $this->app->instance(TokenomixClient::class, $client);

        $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", ['content' => 'clone budi/web, ganti judul, push'])
            ->assertOk()->streamedContent();

        $project = Project::where('name', 'Web Budi')->firstOrFail();
        $this->assertSame($project->id, $conv->fresh()->project_id);
        $this->assertSame('budi/web', $project->github_repo);

        $paths = $project->files()->pluck('content', 'path')->all();
        $this->assertSame('<h1>Edit</h1>', $paths['index.html']);
        $this->assertSame('h1{}', $paths['css/style.css']);
        $this->assertArrayNotHasKey('logo.png', $paths); // binaries skipped

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/git/commits') && $req->method() === 'POST' && $req['message'] === 'update');
    }
}
