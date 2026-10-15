<?php

namespace Tests\Feature;

use App\Models\AgentConfig;
use App\Models\ModelMultiplier;
use App\Models\TokenLedger;
use App\Models\User;
use App\Services\Usage\TokenAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenomixAgentAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_accounting_formula_and_multipliers(): void
    {
        ModelMultiplier::create([
            'model_id' => 'deepseek-v4.1-flash',
            'multiplier' => 2.5600,
            'source' => 'TokenomixAI Catalog',
        ]);

        $user = User::factory()->create();
        $accounting = app(TokenAccountingService::class);

        // Record 1000 prompt tokens and 500 completion tokens = 1500 raw tokens
        $ledger = $accounting->recordUsage(
            user: $user,
            model: 'deepseek-v4.1-flash',
            idempotencyKey: 'idemp_test_1',
            promptTokens: 1000,
            completionTokens: 500,
            dataSource: 'actual',
            status: 'success'
        );

        $this->assertEquals(1500, $ledger->raw_tokens);
        $this->assertEquals(2.5600, (float) $ledger->multiplier_snapshot);
        // 1500 * 2.56 = 3840
        $this->assertEquals(3840.0, (float) $ledger->weighted_tokens);
        $this->assertEquals('actual', $ledger->data_source);
    }

    public function test_idempotency_key_prevents_duplicate_billing(): void
    {
        ModelMultiplier::create([
            'model_id' => 'glm-5.1',
            'multiplier' => 2.0000,
        ]);

        $user = User::factory()->create();
        $accounting = app(TokenAccountingService::class);

        // First execution
        $ledger1 = $accounting->recordUsage(
            user: $user,
            model: 'glm-5.1',
            idempotencyKey: 'idemp_duplicate_key',
            promptTokens: 500,
            completionTokens: 500
        );

        // Duplicate execution with exact same key
        $ledger2 = $accounting->recordUsage(
            user: $user,
            model: 'glm-5.1',
            idempotencyKey: 'idemp_duplicate_key',
            promptTokens: 500,
            completionTokens: 500
        );

        $this->assertEquals($ledger1->id, $ledger2->id);
        $this->assertEquals(1, TokenLedger::where('idempotency_key', 'idemp_duplicate_key')->count());
    }

    public function test_quota_check_halts_requests_when_exceeded(): void
    {
        $user = User::factory()->create([
            'daily_token_quota' => 1000,
            'monthly_token_quota' => 5000,
        ]);

        $accounting = app(TokenAccountingService::class);

        // Has sufficient quota initially
        $this->assertTrue($accounting->checkQuota($user, 200, 'auto'));

        // Consume all quota
        $accounting->recordUsage(
            user: $user,
            model: 'auto',
            idempotencyKey: 'key_exhaust_quota',
            promptTokens: 600,
            completionTokens: 500 // 1100 tokens, exceeds 1000
        );

        // Now checkQuota should return false
        $this->assertFalse($accounting->checkQuota($user, 100, 'auto'));
    }

    public function test_api_key_is_masked_in_admin_response(): void
    {
        AgentConfig::create([
            'name' => 'TokenomixAI Secure',
            'base_url' => 'https://api.tokenomixai.com/v1',
            'api_key_encrypted' => 'sk-tkx-secret-very-long-production-key-12345',
            'default_model' => 'glm-5.1',
            'allowed_models' => ['glm-5.1'],
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/agent');
        $response->assertStatus(200);

        $json = $response->json('config');
        $this->assertArrayNotHasKey('api_key', $json);
        $this->assertArrayNotHasKey('api_key_encrypted', $json);
        $this->assertTrue($json['has_api_key']);
        $this->assertStringContainsString('••••', $json['masked_api_key']);
        $this->assertStringNotContainsString('very-long-production-key', $json['masked_api_key']);
    }
}
