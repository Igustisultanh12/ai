<?php

namespace App\Services\Usage;

use App\Models\ModelMultiplier;
use App\Models\TokenLedger;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TokenAccountingService
{
    /**
     * Get multiplier for a given model from database catalog.
     * Defaults to 1.0000 if not found.
     */
    public function getMultiplier(string $modelId): float
    {
        $multiplier = ModelMultiplier::where('model_id', $modelId)->value('multiplier');
        return $multiplier !== null ? (float) $multiplier : 1.0000;
    }

    /**
     * Check if user has sufficient quota for estimated tokens.
     */
    public function checkQuota(User $user, int $estimatedRawTokens = 2000, string $modelId = 'glm-5.1'): bool
    {
        $multiplier = $this->getMultiplier($modelId);
        $estimatedWeighted = $estimatedRawTokens * $multiplier;

        // Calculate today's usage
        $todayUsed = (float) TokenLedger::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->where('status', 'success')
            ->sum('weighted_tokens');

        if (($todayUsed + $estimatedWeighted) > $user->daily_token_quota) {
            return false;
        }

        // Calculate this month's usage
        $monthUsed = (float) TokenLedger::where('user_id', $user->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('status', 'success')
            ->sum('weighted_tokens');

        if (($monthUsed + $estimatedWeighted) > $user->monthly_token_quota) {
            return false;
        }

        return true;
    }

    /**
     * Estimate token count from text using character heuristic (approx 4 chars/token in English, ~2 in code/multilingual).
     */
    public function estimateTokenCount(string $text): int
    {
        $length = mb_strlen($text);
        if ($length === 0) {
            return 0;
        }
        return (int) max(1, ceil($length / 3.2));
    }

    /**
     * Record usage into ledger atomically with idempotency protection.
     */
    public function recordUsage(
        User $user,
        string $model,
        string $idempotencyKey,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        string $dataSource = 'actual', // actual, estimated, unknown
        string $status = 'success',
        ?string $projectId = null,
        ?string $conversationId = null,
        ?string $runId = null,
        ?int $latencyMs = null,
        int $attemptCount = 1,
        ?string $errorCode = null,
        ?string $provider = 'tokenomixai'
    ): TokenLedger {
        return DB::transaction(function () use (
            $user,
            $model,
            $idempotencyKey,
            $promptTokens,
            $completionTokens,
            $dataSource,
            $status,
            $projectId,
            $conversationId,
            $runId,
            $latencyMs,
            $attemptCount,
            $errorCode,
            $provider
        ) {
            // Check if record with this idempotency key already exists to prevent double recording
            $existing = TokenLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $multiplier = $this->getMultiplier($model);

            // Handle missing / unknown token usage
            $pTokens = $promptTokens ?? 0;
            $cTokens = $completionTokens ?? 0;

            if ($dataSource === 'unknown' && $pTokens === 0 && $cTokens === 0) {
                // Keep marked as unknown, conservative default fallback
                $pTokens = 100;
                $cTokens = 100;
            }

            $rawTokens = $pTokens + $cTokens;
            // Formula: weighted_tokens = raw_tokens * multiplier
            $weightedTokens = round($rawTokens * $multiplier, 4);

            $correlationId = app()->bound('correlation_id') ? app('correlation_id') : (string) Str::uuid();

            return TokenLedger::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'conversation_id' => $conversationId,
                'run_id' => $runId,
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => $correlationId,
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $pTokens,
                'completion_tokens' => $cTokens,
                'raw_tokens' => $rawTokens,
                'multiplier_snapshot' => $multiplier,
                'weighted_tokens' => $weightedTokens,
                'data_source' => $dataSource,
                'status' => $status,
                'latency_ms' => $latencyMs,
                'attempt_count' => $attemptCount,
                'error_code' => $errorCode,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Get user token usage summary for dashboard.
     */
    public function getUserUsageSummary(User $user): array
    {
        $todayUsed = (float) TokenLedger::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->where('status', 'success')
            ->sum('weighted_tokens');

        $monthUsed = (float) TokenLedger::where('user_id', $user->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('status', 'success')
            ->sum('weighted_tokens');

        $totalUsed = (float) TokenLedger::where('user_id', $user->id)
            ->where('status', 'success')
            ->sum('weighted_tokens');

        $remainingDaily = max(0, $user->daily_token_quota - $todayUsed);
        $remainingMonthly = max(0, $user->monthly_token_quota - $monthUsed);

        return [
            'today_weighted' => $todayUsed,
            'month_weighted' => $monthUsed,
            'total_weighted' => $totalUsed,
            'daily_quota' => $user->daily_token_quota,
            'monthly_quota' => $user->monthly_token_quota,
            'remaining_daily' => $remainingDaily,
            'remaining_monthly' => $remainingMonthly,
            'daily_percent' => $user->daily_token_quota > 0 ? min(100, round(($todayUsed / $user->daily_token_quota) * 100, 1)) : 0,
            'monthly_percent' => $user->monthly_token_quota > 0 ? min(100, round(($monthUsed / $user->monthly_token_quota) * 100, 1)) : 0,
        ];
    }
}
