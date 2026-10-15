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
 * https://tokenomixai.com/docs). Every model the gateway returns is offered.
 * Models known locally (model_multipliers) but missing from the live catalog are
 * returned with available=false: per the docs GPT/Claude models are only listed
 * while in stock, so they are shown as "stok habis" instead of disappearing.
 *
 * Multipliers reported by the gateway are synced into model_multipliers so local
 * quota accounting uses the same formula as the gateway:
 * tokens_used = (prompt_tokens + completion_tokens) × multiplier.
 */
class ModelCatalogService
{
    public const CACHE_KEY = 'tokenomix_live_models_v2';
    public const CACHE_TTL_SECONDS = 600;

    /** Model IDs named in the TokenomixAI docs, used only when nothing else is available. */
    public const DOCUMENTED_FALLBACK = [
        'glm-5.1',
        'glm-5.2',
        'glm-5.3-flash',
        'kimi-k3',
        'deepseek-v4-flash-0731',
    ];

    /** Keys under which a gateway may report a model's token multiplier. */
    protected const MULTIPLIER_KEYS = [
        'multiplier', 'token_multiplier', 'tokens_multiplier', 'x_multiplier', 'price_multiplier',
        'quota_multiplier', 'rate', 'pricing.multiplier', 'pricing.token_multiplier', 'metadata.multiplier',
        'meta.multiplier', 'info.multiplier',
    ];

    /**
     * Return ['models' => [...], 'default' => string, 'source' => live|local|fallback].
     */
    public function catalog(): array
    {
        $config = AgentConfig::where('is_active', true)->first() ?: AgentConfig::first();

        $live = $this->liveModels($config); // id => raw model object
        $multipliers = ModelMultiplier::all()->keyBy('model_id');

        if (!empty($live)) {
            $source = 'live';
            $availableIds = array_keys($live);
        } else {
            $availableIds = $multipliers->keys()->reject(fn ($id) => $id === 'auto')->values()->all();
            $source = empty($availableIds) ? 'fallback' : 'local';
            if (empty($availableIds)) {
                $availableIds = self::DOCUMENTED_FALLBACK;
            }
        }

        $models = [];
        foreach ($availableIds as $id) {
            $models[] = $this->present($id, $multipliers->get($id), true);
        }

        // Known models the gateway is not listing right now (e.g. Claude/GPT out of stock)
        if ($source === 'live') {
            foreach ($multipliers as $id => $m) {
                if ($id !== 'auto' && !isset($live[$id])) {
                    $models[] = $this->present($id, $m, false);
                }
            }
        }

        $default = $config?->default_model;
        if (!$default || !in_array($default, $availableIds, true)) {
            $default = $availableIds[0];
        }

        return [
            'models' => $models,
            'default' => $default,
            'source' => $source,
        ];
    }

    public function isAvailable(string $modelId): bool
    {
        foreach ($this->catalog()['models'] as $model) {
            if ($model['id'] === $modelId) {
                return $model['available'];
            }
        }
        return false;
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
     * Upsert model_multipliers from raw /v1/models objects, keeping admin overrides.
     * Returns the number of models processed.
     */
    public function syncMultipliers(array $rawModels): int
    {
        $count = 0;
        foreach ($rawModels as $raw) {
            $id = is_array($raw) ? ($raw['id'] ?? null) : null;
            if (!is_string($id) || $id === '') {
                continue;
            }
            $count++;

            $reported = $this->extractMultiplier($raw);
            $existing = ModelMultiplier::where('model_id', $id)->first();

            if (!$existing) {
                ModelMultiplier::create([
                    'model_id' => $id,
                    'multiplier' => $reported ?? 2.0,
                    'source' => $reported !== null ? 'TokenomixAI /v1/models' : 'TokenomixAI Catalog Sync (multiplier belum diketahui)',
                    'supports_tools' => true,
                    'supports_vision' => false,
                    'description' => is_string($raw['description'] ?? null) ? mb_substr($raw['description'], 0, 255) : null,
                ]);
                continue;
            }

            if ($reported !== null
                && $existing->source !== 'Admin Manual Override'
                && abs((float) $existing->multiplier - $reported) > 0.00001) {
                $existing->update(['multiplier' => $reported, 'source' => 'TokenomixAI /v1/models']);
            }
        }
        return $count;
    }

    public function extractMultiplier(array $raw): ?float
    {
        foreach (self::MULTIPLIER_KEYS as $key) {
            $value = data_get($raw, $key);
            if (is_string($value)) {
                $value = preg_replace('/[^0-9.]/', '', $value);
            }
            if (is_numeric($value) && (float) $value > 0 && (float) $value <= 1000) {
                return round((float) $value, 4);
            }
        }
        return null;
    }

    protected function present(string $id, ?ModelMultiplier $m, bool $available): array
    {
        return [
            'id' => $id,
            'available' => $available,
            'multiplier' => $m ? (float) $m->multiplier : null,
            'description' => $m?->description,
            'supports_tools' => $m ? (bool) $m->supports_tools : null,
            'supports_vision' => $m ? (bool) $m->supports_vision : null,
        ];
    }

    /**
     * @return array<string, array> live models keyed by id, or [] when unavailable
     */
    protected function liveModels(?AgentConfig $config): array
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

            $raw = $client->getModels();
            $byId = [];
            foreach ($raw as $model) {
                $id = is_array($model) ? ($model['id'] ?? null) : null;
                if (is_string($id) && $id !== '') {
                    $byId[$id] = $model;
                }
            }

            if (!empty($byId)) {
                $this->syncMultipliers(array_values($byId));
                Cache::put(self::CACHE_KEY, $byId, self::CACHE_TTL_SECONDS);
            }

            return $byId;
        } catch (Throwable $e) {
            Log::warning('TokenomixAI model catalog unavailable: ' . $e->getMessage());
            return [];
        }
    }
}
