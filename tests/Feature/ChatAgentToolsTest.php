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

    public function streamChatCompletion(array $payload, callable $callback, ?callable $onTick = null): array
    {
        $this->calls[] = $payload;
        $turn = array_shift($this->turns) ?? ['text' => ''];

        if (!empty($turn['fail'])) {
            if (!empty($turn['text'])) {
                $callback($turn['text'], false, null); // partial answer before the drop
            }
            throw new \App\Exceptions\UpstreamRetryableException('Upstream sementara tidak dapat dihubungi (502 upstream_unavailable). Aman untuk dicoba ulang.');
        }

        if (!empty($turn['text'])) {
            $callback($turn['text'], false, null);
        }
        $callback('', false, ['prompt_tokens' => 100, 'completion_tokens' => 20]);
        $callback('', true, null);

        $toolCalls = [];
        foreach ($turn['tools'] ?? [] as $i => [$name, $args]) {
            $toolCalls[] = ['id' => "call_{$i}", 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]];
        }

        return ['tool_calls' => $toolCalls, 'finish_reason' => $turn['finish'] ?? ($toolCalls ? 'tool_calls' : 'stop'), 'aborted' => false];
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

    public function test_upstream_502_is_retried_without_losing_progress(): void
    {
        config(['services.tokenomix.retry_base_seconds' => 0]);
        $user = $this->user();
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);
        $this->fakeRepo();

        $client = new ScriptedTokenomixClient([
            ['tools' => [['github_list_files', ['repo' => 'budi/web']]]],
            ['fail' => true, 'text' => 'Sebagian jawaban yang terpotong'],
            ['fail' => true],
            ['text' => 'Repo berisi 3 berkas.'],
        ]);
        $this->app->instance(TokenomixClient::class, $client);

        $body = $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", ['content' => 'cek repo budi/web'])
            ->assertOk()->streamedContent();

        $this->assertCount(4, $client->calls);
        $this->assertStringContainsString('"event":"retry"', $body);

        $reply = Message::where('conversation_id', $conv->id)->where('role', 'assistant')->first();
        $this->assertSame('completed', $reply->status);
        $this->assertStringContainsString('Repo berisi 3 berkas.', $reply->content);
        $this->assertStringNotContainsString('Sebagian jawaban yang terpotong', $reply->content);
        $this->assertStringContainsString('Melihat isi repo budi/web', $reply->content);
    }

    public function test_gives_up_after_max_attempts_and_keeps_activity(): void
    {
        config(['services.tokenomix.retry_base_seconds' => 0]);
        $user = $this->user();
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);
        $this->fakeRepo();

        $client = new ScriptedTokenomixClient([
            ['tools' => [['github_list_files', ['repo' => 'budi/web']]]],
            ['fail' => true], ['fail' => true], ['fail' => true], ['fail' => true],
        ]);
        $this->app->instance(TokenomixClient::class, $client);

        $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", ['content' => 'cek repo'])
            ->assertOk()->streamedContent();

        $reply = Message::where('conversation_id', $conv->id)->where('role', 'assistant')->first();
        $this->assertSame('failed', $reply->status);
        $this->assertStringContainsString('Melihat isi repo budi/web', $reply->content);
        $this->assertStringContainsString('502 upstream_unavailable', $reply->content);
        $this->assertCount(5, $client->calls);
    }

    public function test_truncated_tool_call_is_not_executed_and_max_tokens_is_sent(): void
    {
        $user = $this->user();
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'x', 'model' => 'glm-5.1']);
        $this->fakeRepo();

        $client = new ScriptedTokenomixClient([
            ['finish' => 'length', 'tools' => [['github_push_files', ['repo' => 'budi/web', 'commit_message' => 'x', 'files' => [['path' => 'a.html', 'content' => '<h1']]]]]],
            ['tools' => [['github_push_files', ['repo' => 'budi/web']]]], // files missing
            ['text' => 'Oke, saya bagi jadi bagian kecil.'],
        ]);
        $this->app->instance(TokenomixClient::class, $client);

        $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", ['content' => 'ubah repo budi/web lalu push'])
            ->assertOk()->streamedContent();

        $this->assertSame(16384, $client->calls[0]['max_tokens']);

        $toolMsgs = array_values(array_filter($client->calls[2]['messages'], fn ($m) => $m['role'] === 'tool'));
        $this->assertStringContainsString('terpotong karena batas panjang output', $toolMsgs[0]['content']);
        $this->assertStringContainsString('Argumen kosong', $toolMsgs[1]['content']);

        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/git/blobs'));
    }

    public function test_step_limit_signals_auto_continue_and_drops_dot_filler(): void
    {
        config(['services.tokenomix.agent_max_steps' => 5]);
        $user = $this->user();
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'Percakapan Baru', 'model' => 'glm-5.1']);
        $this->fakeRepo();

        $turns = [];
        for ($i = 0; $i < 6; $i++) {
            $turns[] = ['text' => '...', 'tools' => [['github_list_files', ['repo' => 'budi/web']]]];
        }
        $client = new ScriptedTokenomixClient($turns);
        $this->app->instance(TokenomixClient::class, $client);

        $res = $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", ['content' => 'kerjakan semua']);
        $body = $res->streamedContent();

        $this->assertCount(5, $client->calls);
        $this->assertStringContainsString('"event":"step_limit"', $body);
        $this->assertStringNotContainsString('"content":"..."', $body);

        $reply = Message::where('conversation_id', $conv->id)->where('role', 'assistant')->first();
        $this->assertSame('completed', $reply->status);
        $this->assertStringNotContainsString("\n...", $reply->content);
        $this->assertStringContainsString('dilanjutkan otomatis', $reply->content);
    }

    public function test_agent_waits_for_ci_reads_failure_fixes_and_waits_again(): void
    {
        config(['services.tokenomix.ci_poll_seconds' => 0]);
        $user = $this->user();
        $conv = Conversation::create(['user_id' => $user->id, 'title' => 'Percakapan Baru', 'model' => 'glm-5.1']);

        $run = fn (int $id, string $conclusion) => [
            'id' => $id, 'name' => 'Android CI', 'status' => 'completed', 'conclusion' => $conclusion, 'event' => 'push',
            'head_branch' => 'main', 'head_sha' => 'c2abcdef00000000000000000000000000000000',
            'html_url' => "https://github.com/budi/web/actions/runs/{$id}", 'run_started_at' => '2026-10-16T00:00:00Z',
        ];
        Http::fake([
            'api.github.com/repos/budi/web/actions/runs?*' => Http::sequence()
                ->push(['workflow_runs' => [$run(77, 'failure')]])
                ->push(['workflow_runs' => [$run(78, 'success')]]),
            'api.github.com/repos/budi/web/actions/runs/77/jobs*' => Http::response(['jobs' => [
                ['id' => 501, 'name' => 'build', 'conclusion' => 'failure', 'steps' => [['name' => 'Gradle build', 'conclusion' => 'failure']]],
            ]]),
            'api.github.com/repos/budi/web/check-runs/501/annotations*' => Http::response([]),
            'api.github.com/repos/budi/web/actions/jobs/501/logs' => Http::response("e: MainActivity.kt:12:5 Unresolved reference: Text\n"),
            'api.github.com/repos/budi/web/actions/runs/78/artifacts*' => Http::response(['artifacts' => [['name' => 'app-debug.apk', 'size_in_bytes' => 1]]]),
            'api.github.com/repos/budi/web/releases*' => Http::response([]),
        ]);
        $this->fakeRepo();

        $push = ['github_push_files', ['repo' => 'budi/web', 'commit_message' => 'feat: app', 'files' => [['path' => 'MainActivity.kt', 'content' => 'x']]]];
        $client = new ScriptedTokenomixClient([
            ['tools' => [$push]],
            ['tools' => [['github_wait_workflow', ['repo' => 'budi/web', 'head_sha' => 'c2abcdef']]]],
            ['tools' => [['github_workflow_logs', ['repo' => 'budi/web', 'run_id' => 77]]]],
            ['tools' => [$push]],
            ['tools' => [['github_wait_workflow', ['repo' => 'budi/web', 'head_sha' => 'c2abcdef']]]],
            ['text' => 'Build sukses, APK tersedia.'],
        ]);
        $this->app->instance(TokenomixClient::class, $client);

        $body = $this->actingAs($user)->post("/api/conversations/{$conv->id}/stream", ['content' => 'buat aplikasinya sampai jadi'])->streamedContent();

        $this->assertCount(6, $client->calls);
        $toolMsgs = array_values(array_filter($client->calls[3]['messages'], fn ($m) => $m['role'] === 'tool'));
        $this->assertStringContainsString('Unresolved reference: Text', end($toolMsgs)['content']);
        $lastTool = array_values(array_filter($client->calls[5]['messages'], fn ($m) => $m['role'] === 'tool'));
        $this->assertStringContainsString('app-debug.apk', end($lastTool)['content']);

        $reply = Message::where('conversation_id', $conv->id)->where('role', 'assistant')->first();
        $this->assertStringContainsString('**[GAGAL]** Menunggu GitHub Actions budi/web @c2abcde: gagal', $reply->content);
        $this->assertStringContainsString('**[OK]** Menunggu GitHub Actions budi/web @c2abcde: berhasil', $reply->content);
        $this->assertStringContainsString('https://github.com/budi/web/actions/runs/78', $reply->content);
        $this->assertStringContainsString('Build sukses', $reply->content);
    }
}
