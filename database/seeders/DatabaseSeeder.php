<?php

namespace Database\Seeders;

use App\Models\AgentConfig;
use App\Models\ModelMultiplier;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Initial Agent Config (without hardcoded API keys)
        if (AgentConfig::count() === 0) {
            AgentConfig::create([
                'name' => 'TokenomixAI Primary',
                'provider' => 'tokenomixai',
                'base_url' => 'https://api.tokenomixai.com/v1',
                'api_key_encrypted' => null, // Configured securely via Admin UI or bootstrap command
                'default_model' => 'glm-5.1',
                'allowed_models' => [
                    'glm-5.1',
                    'glm-5.2',
                    'glm-5.3',
                    'glm-5.3-flash',
                    'deepseek-v4.1-flash',
                    'deepseek-v4-flash',
                    'kimi-k2.8',
                    'kimi-k3',
                    'mimo-v2.6-pro',
                    'auto',
                ],
                'max_output_tokens' => 4096,
                'temperature' => 0.70,
                'timeout_seconds' => 60,
                'concurrency_limit' => 5,
                'max_iterations' => 5,
                'is_active' => false, // Inactive until API key is provided
            ]);
        }

        // 2. Seed TokenomixAI Official Catalog Multipliers
        $catalog = [
            ['model_id' => 'glm-5.1', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => false, 'description' => 'Coding Workhorse, OpenAI compatible'],
            ['model_id' => 'glm-5.2', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => false, 'description' => 'GLM 5.2 balanced model'],
            ['model_id' => 'glm-5.3', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => false, 'description' => 'Lini GLM, andalan untuk ngoding seharian'],
            ['model_id' => 'glm-5.3-flash', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => false, 'description' => 'GLM Flash latency rendah'],
            ['model_id' => 'deepseek-v4.1-flash', 'multiplier' => 2.5600, 'supports_tools' => true, 'supports_vision' => true, 'description' => 'Speed King, respon kilat untuk kerja harian'],
            ['model_id' => 'deepseek-v4-flash', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => false, 'description' => 'Paling cepat untuk jawaban singkat'],
            ['model_id' => 'deepseek-v4-flash-0731', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => false, 'description' => 'DeepSeek snapshot 0731'],
            ['model_id' => 'deepseek-v4-flash-vision-exp', 'multiplier' => 2.5000, 'supports_tools' => true, 'supports_vision' => true, 'description' => 'Varian eksperimental vision'],
            ['model_id' => 'kimi-k2.8', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => true, 'description' => 'Lini Kimi, siap untuk agent dan tugas panjang'],
            ['model_id' => 'kimi-k3', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => false, 'description' => 'Kimi K3 reasoning engine'],
            ['model_id' => 'gpt-6-astra', 'multiplier' => 42.0000, 'supports_tools' => true, 'supports_vision' => true, 'description' => 'Varian puncak lini GPT, Frontier Flagship'],
            ['model_id' => 'claude-opus-5-5', 'multiplier' => 32.0000, 'supports_tools' => true, 'supports_vision' => true, 'description' => 'Model paling premium di katalog, The Crown Jewel'],
            ['model_id' => 'claude-opus-5', 'multiplier' => 20.0000, 'supports_tools' => true, 'supports_vision' => true, 'description' => 'Premium untuk kerja kompleks'],
            ['model_id' => 'mimo-v2.6-pro', 'multiplier' => 2.0000, 'supports_tools' => true, 'supports_vision' => true, 'description' => 'Hidden Gem, performa tinggi dengan multiplier bersahabat'],
            ['model_id' => 'auto', 'multiplier' => 1.0000, 'supports_tools' => true, 'supports_vision' => false, 'description' => 'Auto - Pilihan serba guna untuk mulai mencoba'],
        ];

        foreach ($catalog as $item) {
            ModelMultiplier::updateOrCreate(
                ['model_id' => $item['model_id']],
                [
                    'multiplier' => $item['multiplier'],
                    'source' => 'TokenomixAI Catalog',
                    'supports_tools' => $item['supports_tools'],
                    'supports_vision' => $item['supports_vision'],
                    'description' => $item['description'],
                ]
            );
        }
    }
}
