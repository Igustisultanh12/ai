<?php

namespace App\Services\GitHub;

use App\Models\User;
use Exception;

/**
 * GitHub Actions for the chat agent: find the workflow runs of a commit, wait until
 * they finish, read why a run failed (annotations + log tail), and re-run jobs.
 * Lets the agent loop "push -> wait for CI -> read errors -> fix -> push" by itself.
 */
class GitHubActionsService
{
    /** @var callable(int): void */
    protected $sleeper;

    public function __construct(protected GitHubService $github)
    {
        $this->sleeper = fn (int $seconds) => sleep($seconds);
    }

    /** Tests replace the real sleep so polling runs instantly. */
    public function setSleeper(callable $sleeper): void
    {
        $this->sleeper = $sleeper;
    }

    /**
     * Latest workflow runs, optionally for one branch or commit.
     */
    public function listRuns(User $user, string $repo, ?string $branch = null, ?string $headSha = null, int $limit = 10): array
    {
        [$repoFullName, $http] = $this->github->apiClient($user, $repo);

        $query = array_filter([
            'branch' => $branch ?: null,
            'head_sha' => $headSha && strlen($headSha) === 40 ? $headSha : null,
            'per_page' => $headSha ? 30 : $limit,
        ]);
        $res = $http()->get("https://api.github.com/repos/{$repoFullName}/actions/runs", $query);
        if (!$res->successful()) {
            throw new Exception($this->actionsError($repoFullName, $res->status(), (string) $res->json('message')));
        }

        $runs = array_map(fn ($r) => $this->summarizeRun($r), (array) $res->json('workflow_runs'));
        if ($headSha) {
            // Short SHAs cannot be filtered server-side
            $runs = array_values(array_filter($runs, fn ($r) => str_starts_with($r['head_sha'], strtolower($headSha))));
        }

        return ['repo' => $repoFullName, 'runs' => array_slice($runs, 0, $limit)];
    }

    /**
     * Wait until every workflow run of a commit (or one run) has finished.
     *
     * @param callable():void $tick       called about once a second (keeps the chat stream alive)
     * @param callable():bool $shouldStop true when the user pressed stop
     * @param callable(string):void $onStatus short progress text for the activity panel
     */
    public function waitForRuns(
        User $user,
        string $repo,
        ?string $headSha = null,
        ?int $runId = null,
        ?string $branch = null,
        ?int $maxSeconds = null,
        ?callable $tick = null,
        ?callable $shouldStop = null,
        ?callable $onStatus = null
    ): array {
        [$repoFullName, $http] = $this->github->apiClient($user, $repo);
        $api = "https://api.github.com/repos/{$repoFullName}";
        $maxSeconds ??= (int) config('services.tokenomix.ci_wait_seconds', 900);
        $poll = max(0, (int) config('services.tokenomix.ci_poll_seconds', 10));
        $appearSeconds = (int) config('services.tokenomix.ci_appear_seconds', 90);
        $tick ??= fn () => null;
        $shouldStop ??= fn () => false;
        $onStatus ??= fn (string $s) => null;

        $started = time();
        $runIds = $runId ? [$runId] : [];
        $runs = [];

        while (true) {
            if ($shouldStop()) {
                return ['status' => 'stopped', 'message' => 'Menunggu workflow dihentikan oleh pengguna.'];
            }

            if ($runIds) {
                $runs = [];
                foreach ($runIds as $id) {
                    $res = $http()->get("{$api}/actions/runs/{$id}");
                    if (!$res->successful()) {
                        throw new Exception($this->actionsError($repoFullName, $res->status(), (string) $res->json('message')));
                    }
                    $runs[] = $this->summarizeRun($res->json());
                }
            } else {
                // The run for a fresh push can take a few seconds to show up
                $found = $this->listRuns($user, $repoFullName, $headSha ? null : $branch, $headSha, 30)['runs'];
                if (!$headSha && $found) {
                    $latestSha = $found[0]['head_sha'];
                    $found = array_values(array_filter($found, fn ($r) => $r['head_sha'] === $latestSha));
                }
                if ($found) {
                    $runIds = array_column($found, 'id');
                    $runs = $found;
                } elseif (time() - $started >= $appearSeconds) {
                    return [
                        'status' => 'not_found',
                        'repo' => $repoFullName,
                        'message' => 'Tidak ada workflow run untuk ' . ($headSha ? 'commit ' . substr($headSha, 0, 7) : 'branch ' . ($branch ?: 'default'))
                            . '. Pastikan repo punya .github/workflows/*.yml dengan trigger "push" (atau workflow_dispatch) untuk branch ini, dan GitHub Actions aktif di repo.',
                    ];
                } else {
                    $onStatus('menunggu workflow muncul');
                }
            }

            if ($runs && $this->allCompleted($runs)) {
                return $this->finalResult($http, $api, $repoFullName, $runs);
            }

            if ($runs) {
                $onStatus($this->progressText($runs, time() - $started));
            }

            if (time() - $started >= $maxSeconds) {
                return [
                    'status' => 'still_running',
                    'repo' => $repoFullName,
                    'runs' => $runs,
                    'message' => 'Workflow masih berjalan setelah ' . intdiv($maxSeconds, 60) . ' menit. Panggil github_wait_workflow lagi dengan run_id yang sama untuk terus menunggu.',
                ];
            }

            for ($i = 0; $i < $poll; $i++) {
                if ($shouldStop()) {
                    break;
                }
                ($this->sleeper)(1);
                $tick();
            }
        }
    }

    /**
     * Why a run failed: failed jobs/steps, error annotations and the end of each failed job log.
     */
    public function failureDetails(User $user, string $repo, int $runId, int $maxLogChars = 9000): array
    {
        [$repoFullName, $http] = $this->github->apiClient($user, $repo);
        $api = "https://api.github.com/repos/{$repoFullName}";

        $jobsRes = $http()->get("{$api}/actions/runs/{$runId}/jobs", ['per_page' => 50, 'filter' => 'latest']);
        if (!$jobsRes->successful()) {
            throw new Exception($this->actionsError($repoFullName, $jobsRes->status(), (string) $jobsRes->json('message')));
        }

        $jobs = [];
        $budget = 20000;
        foreach ((array) $jobsRes->json('jobs') as $job) {
            $conclusion = $job['conclusion'] ?? null;
            if (!in_array($conclusion, ['failure', 'timed_out', 'cancelled', 'startup_failure', 'action_required'], true)) {
                continue;
            }

            $failedSteps = array_values(array_map(
                fn ($s) => $s['name'],
                array_filter((array) ($job['steps'] ?? []), fn ($s) => in_array($s['conclusion'] ?? null, ['failure', 'timed_out', 'cancelled'], true))
            ));

            // Check-run annotations hold compiler/test errors with file and line
            $annotations = [];
            $annRes = $http()->get("{$api}/check-runs/{$job['id']}/annotations", ['per_page' => 30]);
            if ($annRes->successful()) {
                foreach ((array) $annRes->json() as $a) {
                    $annotations[] = trim(($a['annotation_level'] ?? '') . ' ' . ($a['path'] ?? '') . (isset($a['start_line']) ? ':' . $a['start_line'] : '') . ' ' . ($a['message'] ?? ''));
                }
            }

            $log = '';
            if ($budget > 0) {
                $logRes = $http()->timeout(60)->get("{$api}/actions/jobs/{$job['id']}/logs");
                if ($logRes->successful()) {
                    $log = $this->extractLog($logRes->body(), min($maxLogChars, $budget));
                    $budget -= strlen($log);
                } else {
                    $log = "(log tidak bisa dibaca: HTTP {$logRes->status()})";
                }
            }

            $jobs[] = [
                'job' => $job['name'] ?? (string) $job['id'],
                'conclusion' => $conclusion,
                'failed_steps' => $failedSteps,
                'annotations' => array_slice($annotations, 0, 30),
                'log' => $log,
                'url' => $job['html_url'] ?? null,
            ];
        }

        return [
            'repo' => $repoFullName,
            'run_id' => $runId,
            'failed_jobs' => $jobs,
            'note' => $jobs ? null : 'Tidak ada job yang gagal di run ini.',
        ];
    }

    public function rerun(User $user, string $repo, int $runId, bool $failedOnly = true): array
    {
        [$repoFullName, $http] = $this->github->apiClient($user, $repo);
        $path = $failedOnly ? 'rerun-failed-jobs' : 'rerun';
        $res = $http()->post("https://api.github.com/repos/{$repoFullName}/actions/runs/{$runId}/{$path}");
        if (!$res->successful()) {
            throw new Exception($this->actionsError($repoFullName, $res->status(), (string) $res->json('message'), true));
        }

        return ['repo' => $repoFullName, 'run_id' => $runId, 'rerun' => true, 'url' => "https://github.com/{$repoFullName}/actions/runs/{$runId}"];
    }

    /**
     * Keep the useful part of a job log: error lines with some context, plus the tail.
     */
    public function extractLog(string $raw, int $maxChars): string
    {
        $lines = preg_split('/\r?\n/', $raw);
        // Drop the ISO timestamp GitHub prefixes to every line
        $lines = array_map(fn ($l) => preg_replace('/^\d{4}-\d{2}-\d{2}T[\d:.]+Z\s?/', '', $l), $lines);

        $keep = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/##\[error\]|\berror\b|FAILED|FAILURE|Exception|e: file:|What went wrong|exit code [1-9]/i', $line)) {
                for ($j = max(0, $i - 3); $j <= min(count($lines) - 1, $i + 6); $j++) {
                    $keep[$j] = true;
                }
            }
        }
        $errorPart = '';
        $last = -2;
        foreach (array_keys($keep) as $j) {
            $errorPart .= ($j !== $last + 1 ? "...\n" : '') . $lines[$j] . "\n";
            $last = $j;
        }

        $tail = implode("\n", array_slice($lines, -60));
        $out = ($errorPart !== '' ? "== Baris error ==\n" . $errorPart . "\n" : '') . "== Akhir log ==\n" . $tail;

        if (strlen($out) > $maxChars) {
            $half = intdiv($maxChars, 2);
            $out = substr($out, 0, $half) . "\n...[dipotong]...\n" . substr($out, -$half);
        }

        return $out;
    }

    protected function summarizeRun(array $r): array
    {
        return [
            'id' => $r['id'] ?? null,
            'workflow' => $r['name'] ?? null,
            'status' => $r['status'] ?? null,
            'conclusion' => $r['conclusion'] ?? null,
            'event' => $r['event'] ?? null,
            'branch' => $r['head_branch'] ?? null,
            'head_sha' => strtolower((string) ($r['head_sha'] ?? '')),
            'url' => $r['html_url'] ?? null,
            'started_at' => $r['run_started_at'] ?? ($r['created_at'] ?? null),
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }

    protected function allCompleted(array $runs): bool
    {
        foreach ($runs as $r) {
            if (($r['status'] ?? null) !== 'completed') {
                return false;
            }
        }
        return true;
    }

    protected function progressText(array $runs, int $elapsed): string
    {
        $parts = array_map(fn ($r) => ($r['workflow'] ?? 'workflow') . ': ' . str_replace('_', ' ', (string) $r['status']), $runs);
        return implode(', ', $parts) . ' · ' . sprintf('%d:%02d', intdiv($elapsed, 60), $elapsed % 60);
    }

    protected function finalResult(\Closure $http, string $api, string $repoFullName, array $runs): array
    {
        $failed = array_values(array_filter($runs, fn ($r) => !in_array($r['conclusion'], ['success', 'skipped', 'neutral'], true)));
        $result = [
            'status' => 'completed',
            'conclusion' => $failed ? 'failure' : 'success',
            'repo' => $repoFullName,
            'runs' => $runs,
        ];

        if ($failed) {
            $result['failed_run_ids'] = array_column($failed, 'id');
            $result['next_step'] = 'Panggil github_workflow_logs dengan run_id yang gagal untuk membaca penyebabnya, perbaiki kodenya, push, lalu tunggu lagi.';
            return $result;
        }

        // What the run produced: build artifacts and any release made from this commit
        $artifacts = [];
        foreach ($runs as $r) {
            $res = $http()->get("{$api}/actions/runs/{$r['id']}/artifacts", ['per_page' => 20]);
            foreach ((array) ($res->successful() ? $res->json('artifacts') : []) as $a) {
                $artifacts[] = ['name' => $a['name'] ?? null, 'size_bytes' => $a['size_in_bytes'] ?? null, 'run_url' => $r['url']];
            }
        }
        $result['artifacts'] = $artifacts;

        $rel = $http()->get("{$api}/releases", ['per_page' => 3]);
        if ($rel->successful()) {
            $runStart = strtotime((string) ($runs[0]['started_at'] ?? '')) ?: 0;
            foreach ((array) $rel->json() as $release) {
                $assets = array_map(fn ($a) => ['name' => $a['name'] ?? null, 'url' => $a['browser_download_url'] ?? null], (array) ($release['assets'] ?? []));
                $result['latest_release'] = [
                    'tag' => $release['tag_name'] ?? null,
                    'url' => $release['html_url'] ?? null,
                    'published_at' => $release['published_at'] ?? null,
                    'assets' => $assets,
                    // Published after this run started: most likely created by it
                    'made_by_this_run' => $runStart > 0 && (strtotime((string) ($release['published_at'] ?? '')) ?: 0) >= $runStart,
                ];
                break;
            }
        }

        return $result;
    }

    protected function actionsError(string $repoFullName, int $status, string $message, bool $write = false): string
    {
        $msg = "GitHub Actions di {$repoFullName} tidak bisa diakses (HTTP {$status})" . ($message !== '' ? ": {$message}" : '') . '.';
        if (in_array($status, [401, 403, 404], true)) {
            $msg .= ' Token perlu izin Actions: fine-grained token -> Actions = ' . ($write ? 'Read and write' : 'Read-only atau lebih')
                . '; classic token -> scope "repo" (dan "workflow" untuk mengubah berkas workflow). Perbarui token di menu Pengaturan & GitHub.';
        }
        return $msg;
    }
}
