<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_probe_returns_ok(): void
    {
        $response = $this->getJson('/health');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['status', 'timestamp']);
    }

    public function test_readiness_probe_returns_subsystem_statuses(): void
    {
        $response = $this->getJson('/health/ready');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database',
                    'cache',
                    'storage',
                    'tokenomix_provider',
                    'rar_archiver',
                ],
                'timestamp',
            ]);
    }
}
