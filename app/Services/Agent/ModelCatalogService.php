<?php

namespace App\Services\Agent;

use App\Models\AgentConfig;
use App\Models\ModelMultiplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds the list of chat models a user may pick from.
 *
 * Source of truth is the live TokenomixAI catalog (GET /v1/models, see
 * https://tokenomixai.com/docs). When the gateway is unreachable or no API key
 * is configured, the locally synced model_multipliers table is used instead,
 * and as a last resort the model IDs documented by TokenomixAI.
 */
class ModelCatalogService
{
    public const CACHE_KEY = 'tokenomix_live_models_v1';
    public const CACHE_TTL_SECONDS = 600;

    /** Model IDs named in the TokenomixAI docs, used only when nothing else is available. */
    public const DOCUMENTED_FALLBACK = [
        'glm-5.1',
        'glm-5.2',
        'glm-5.3-flash',
        'kimi-k3',
        'deepseek-v4-flash-0731',
    ];

    /**
     * Return ['models' => [...], 'default' => string, 'source' => live|local|fallback].
     */
    public function catalog(): array
    {
        $config = AgentConfig::where('is_active', true)->first() ?: AgentConfig::first();
        $multipliers = ModelMultiplier::all()->keyBy('model_id');

        $live = $this->liveModelIds($config);
        $source = 'live';
        $ids = $live;

        if (empty($ids)) {
            $source = 'local';
            // "auto" is only meaningful for Hermes configs per the docs, never as a chat model.
            $ids = $multipliers->keys()->reject(fn ($id) => $id === 'auto')->values()->all();
        }

        if (empty($ids)) {
            $source = 'fallback';
            $ids = self::DOCUMENTED_FALLBACK;
        }

        // Admin allow-list narrows the catalog, but never empties it because of stale IDs.
        $allowed = array_filter((array) ($config?->allowed_models ?? []));
        if (!empty($allowed)) {
            $narrowed = array_values(array_intersect($ids, $allowed));
            if (!empty($narrowed)) {
                $ids = $narrowed;
            }
        }

        $ids = array_values(array_unique($ids));

        $models = array_map(function (string $id) use ($multipliers) {
            $m = $multipliers->get($id);
            return [
                'id' => $id,
                'multiplier' => $m ? (float) $m->multiplier : null,
                'description' => $m?->description,
                'supports_tools' => $m ? (bool) $m->supports_tools : null,
                'supports_vision' => $m ? (bool) $m->supports_vision : null,
            ];
        }, $ids);

        $default = $config?->default_model;
        if (!$default || !in_array($default, $ids, true)) {
            $default = $ids[0];
        }

        return [
            'models' => $models,
            'default' => $default,
            'source' => $source,
        ];
    }

    public function isAvailable(string $modelId): bool
    {
        return in_array($modelId, array_column($this->catalog()['models'], 'id'), true);
    }

    public function defaultModel(): string
    {
        return $this->catalog()['default'];
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return string[] live model IDs, or [] when unavailable
     */
    protected function liveModelIds(?AgentConfig $config): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        try {
            $client = new TokenomixClient($config);
            if (!$client->isConfigured()) {
                return [];
            }

            $ids = [];
            foreach ($client->getModels() as $model) {
                $id = is_array($model) ? ($model['id'] ?? null) : null;
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }

            if (!empty($ids)) {
                Cache::put(self::CACHE_KEY, $ids, self::CACHE_TTL_SECONDS);
            }

            return $ids;
        } catch (Throwable $e) {
            Log::warning('TokenomixAI model catalog unavailable: ' . $e->getMessage());
            return [];
        }
    }
}
