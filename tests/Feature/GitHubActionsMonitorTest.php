<?php

namespace Tests\Feature;

use App\Models\GitHubConnection;
use App\Models\User;
use App\Services\GitHub\GitHubActionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubActionsMonitorTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'abcdef1234567890abcdef1234567890abcdef12';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tokenomix.ci_poll_seconds' => 0, 'services.tokenomix.ci_appear_seconds' => 60]);
    }

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

    protected function fakeRun(string $status, ?string $conclusion): array
    {
        return [
            'id' => 77, 'name' => 'Build APK', 'status' => $status, 'conclusion' => $conclusion,
            'event' => 'push', 'head_branch' => 'main', 'head_sha' => self::SHA,
            'html_url' => 'https://github.com/budi/app/actions/runs/77', 'run_started_at' => '2026-10-16T00:00:00Z',
        ];
    }

    public function test_waits_for_the_run_of_a_commit_until_it_fails(): void
    {
        Http::fake([
            'api.github.com/repos/budi/app/actions/runs/77' => Http::sequence()
                ->push($this->fakeRun('in_progress', null))
                ->push($this->fakeRun('completed', 'failure')),
            'api.github.com/repos/budi/app/actions/runs?*' => Http::sequence()
                ->push(['workflow_runs' => []])                              // not created yet
                ->push(['workflow_runs' => [$this->fakeRun('queued', null)]]),
        ]);

        $statuses = [];
        $result = app(GitHubActionsService::class)->waitForRuns(
            $this->user(), 'https://github.com/budi/app', self::SHA,
            onStatus: function ($s) use (&$statuses) { $statuses[] = $s; }
        );

        $this->assertSame('completed', $result['status']);
        $this->assertSame('failure', $result['conclusion']);
        $this->assertSame([77], $result['failed_run_ids']);
        $this->assertContains('menunggu workflow muncul', $statuses);
        $this->assertTrue(collect($statuses)->contains(fn ($s) => str_contains($s, 'Build APK: in progress')));
    }

    public function test_success_reports_artifacts_and_release(): void
    {
        Http::fake([
            'api.github.com/repos/budi/app/actions/runs/77/artifacts*' => Http::response(['artifacts' => [['name' => 'app-release.apk', 'size_in_bytes' => 5000]]]),
            'api.github.com/repos/budi/app/actions/runs/77' => Http::response($this->fakeRun('completed', 'success')),
            'api.github.com/repos/budi/app/releases*' => Http::response([[
                'tag_name' => 'v1.0.0', 'html_url' => 'https://github.com/budi/app/releases/tag/v1.0.0',
                'published_at' => '2026-10-16T00:05:00Z',
                'assets' => [['name' => 'app.apk', 'browser_download_url' => 'https://github.com/budi/app/releases/download/v1.0.0/app.apk']],
            ]]),
        ]);

        $result = app(GitHubActionsService::class)->waitForRuns($this->user(), 'budi/app', runId: 77);

        $this->assertSame('success', $result['conclusion']);
        $this->assertSame('app-release.apk', $result['artifacts'][0]['name']);
        $this->assertSame('v1.0.0', $result['latest_release']['tag']);
        $this->assertTrue($result['latest_release']['made_by_this_run']);
        $this->assertSame('app.apk', $result['latest_release']['assets'][0]['name']);
    }

    public function test_reports_when_no_workflow_runs_for_the_commit(): void
    {
        config(['services.tokenomix.ci_appear_seconds' => 0]);
        Http::fake(['api.github.com/repos/budi/app/actions/runs*' => Http::response(['workflow_runs' => []])]);

        $result = app(GitHubActionsService::class)->waitForRuns($this->user(), 'budi/app', self::SHA);

        $this->assertSame('not_found', $result['status']);
        $this->assertStringContainsString('.github/workflows', $result['message']);
    }

    public function test_stop_button_ends_the_wait(): void
    {
        Http::fake(['*' => Http::response($this->fakeRun('in_progress', null))]);

        $result = app(GitHubActionsService::class)->waitForRuns($this->user(), 'budi/app', runId: 77, shouldStop: fn () => true);

        $this->assertSame('stopped', $result['status']);
    }

    public function test_failure_details_return_failed_steps_annotations_and_error_lines(): void
    {
        $log = "2026-10-16T00:00:01.0000000Z > Task :app:compileReleaseKotlin\n"
            . str_repeat("2026-10-16T00:00:02.0000000Z noise line\n", 200)
            . "2026-10-16T00:00:03.0000000Z e: file:///app/src/main/java/MainActivity.kt:12:5 Unresolved reference: Text\n"
            . "2026-10-16T00:00:04.0000000Z FAILURE: Build failed with an exception.\n"
            . "2026-10-16T00:00:05.0000000Z ##[error]Process completed with exit code 1.\n";

        Http::fake([
            'api.github.com/repos/budi/app/actions/runs/77/jobs*' => Http::response(['jobs' => [
                ['id' => 501, 'name' => 'build', 'conclusion' => 'failure', 'html_url' => 'https://github.com/budi/app/actions/runs/77/job/501', 'steps' => [
                    ['name' => 'Checkout', 'conclusion' => 'success'],
                    ['name' => 'Build APK', 'conclusion' => 'failure'],
                ]],
                ['id' => 502, 'name' => 'lint', 'conclusion' => 'success', 'steps' => []],
            ]]),
            'api.github.com/repos/budi/app/check-runs/501/annotations*' => Http::response([
                ['annotation_level' => 'failure', 'path' => 'app/src/main/java/MainActivity.kt', 'start_line' => 12, 'message' => 'Unresolved reference: Text'],
            ]),
            'api.github.com/repos/budi/app/actions/jobs/501/logs' => Http::response($log),
        ]);

        $result = app(GitHubActionsService::class)->failureDetails($this->user(), 'budi/app', 77);

        $this->assertCount(1, $result['failed_jobs']);
        $job = $result['failed_jobs'][0];
        $this->assertSame(['Build APK'], $job['failed_steps']);
        $this->assertStringContainsString('MainActivity.kt:12 Unresolved reference', $job['annotations'][0]);
        $this->assertStringContainsString('Unresolved reference: Text', $job['log']);
        $this->assertStringContainsString('== Baris error ==', $job['log']);
        $this->assertStringNotContainsString('2026-10-16T00:00:03', $job['log']);
    }

    public function test_missing_actions_permission_is_explained(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Resource not accessible by personal access token'], 403)]);

        $this->expectExceptionMessage('Actions = Read-only');
        app(GitHubActionsService::class)->listRuns($this->user(), 'budi/app');
    }
}
