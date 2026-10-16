<?php

namespace Tests\Feature;

use App\Models\GitHubConnection;
use App\Models\User;
use App\Services\GitHub\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GitHubCreateRepoTest extends TestCase
{
    use RefreshDatabase;

    protected function user(string $token = 'github_pat_selectedonly'): User
    {
        $user = User::factory()->create();
        GitHubConnection::create([
            'user_id' => $user->id,
            'github_username' => 'Igustisultanh12',
            'token_encrypted' => $token,
            'status' => 'connected',
        ]);
        return $user;
    }

    public function test_404_on_create_is_logged_and_explained(): void
    {
        Log::spy();
        Http::fake([
            'api.github.com/repos/Igustisultanh12/ai-android' => Http::response(['message' => 'Not Found'], 404),
            'api.github.com/user/repos' => Http::response(
                ['message' => 'Not Found', 'documentation_url' => 'https://docs.github.com/rest/repos/repos#create-a-repository-for-the-authenticated-user'],
                404,
                ['X-Accepted-GitHub-Permissions' => 'administration=write', 'X-GitHub-Request-Id' => 'ABC:123']
            ),
        ]);

        try {
            app(GitHubService::class)->createRepository($this->user(), 'ai-android');
            $this->fail('Expected exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('HTTP 404', $e->getMessage());
            $this->assertStringContainsString('Administration = "Read and write"', $e->getMessage());
            $this->assertStringContainsString('All repositories', $e->getMessage());
            $this->assertStringContainsString('https://github.com/new?name=ai-android', $e->getMessage());
        }

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return $message === 'GitHub create repo failed'
                && $context['status'] === 404
                && $context['token_type'] === 'fine-grained'
                && $context['accepted_permissions'] === 'administration=write'
                && $context['github_request_id'] === 'ABC:123'
                && !str_contains(json_encode($context), 'github_pat_selectedonly');
        })->once();
    }

    public function test_existing_repository_is_reused_instead_of_failing(): void
    {
        Http::fake([
            'api.github.com/repos/Igustisultanh12/ai-android' => Http::response([
                'full_name' => 'Igustisultanh12/ai-android',
                'html_url' => 'https://github.com/Igustisultanh12/ai-android',
                'default_branch' => 'main',
                'private' => true,
            ]),
            '*' => Http::response([], 500),
        ]);

        $result = app(GitHubService::class)->createRepository($this->user(), 'ai-android');

        $this->assertSame('Igustisultanh12/ai-android', $result['repo']);
        $this->assertTrue($result['already_existed']);
        Http::assertNotSent(fn ($req) => $req->method() === 'POST');
    }
}
