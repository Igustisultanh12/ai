<?php

namespace Tests\Feature;

use App\Models\GitHubConnection;
use App\Models\User;
use App\Services\GitHub\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubWriteAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        GitHubConnection::create([
            'user_id' => $user->id,
            'github_username' => 'Igustisultanh12',
            'token_encrypted' => 'github_pat_readonly',
            'status' => 'connected',
        ]);
        return $user;
    }

    public function test_read_only_token_is_rejected_before_uploading_anything(): void
    {
        Http::fake([
            'api.github.com/repos/Igustisultanh12/ai' => Http::response(['default_branch' => 'main', 'permissions' => ['pull' => true, 'push' => false]]),
            '*' => Http::response([], 500),
        ]);

        try {
            app(GitHubService::class)->pushFiles($this->user(), 'https://github.com/Igustisultanh12/ai.git', 'main', ['a.css' => 'x'], 'msg');
            $this->fail('Expected exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('TIDAK punya izin menulis', $e->getMessage());
            $this->assertStringContainsString('Contents: Read and write', $e->getMessage());
            $this->assertStringContainsString('bukan format URL', $e->getMessage());
        }

        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/git/blobs'));
    }

    public function test_blob_404_is_explained_as_missing_write_permission(): void
    {
        Http::fake([
            'api.github.com/repos/Igustisultanh12/ai' => Http::response(['default_branch' => 'main']), // permissions not reported
            'api.github.com/repos/Igustisultanh12/ai/git/ref/heads/main' => Http::response(['object' => ['sha' => 'p1']]),
            'api.github.com/repos/Igustisultanh12/ai/git/commits/p1' => Http::response(['tree' => ['sha' => 't1']]),
            'api.github.com/repos/Igustisultanh12/ai/git/blobs' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->expectExceptionMessage('TIDAK punya izin menulis');
        app(GitHubService::class)->pushFiles($this->user(), 'Igustisultanh12/ai', 'main', ['resources/css/app.css' => 'x'], 'msg');
    }

    public function test_listing_files_reports_can_push(): void
    {
        Http::fake([
            'api.github.com/repos/Igustisultanh12/ai' => Http::response(['default_branch' => 'main', 'permissions' => ['push' => false]]),
            'api.github.com/repos/Igustisultanh12/ai/git/trees/main*' => Http::response(['tree' => [['path' => 'a', 'type' => 'blob', 'size' => 1]]]),
        ]);

        $tree = app(GitHubService::class)->getRepositoryTree($this->user(), 'https://github.com/Igustisultanh12/ai.git');
        $this->assertFalse($tree['can_push']);
        $this->assertSame('Igustisultanh12/ai', $tree['repo']);
    }
}
