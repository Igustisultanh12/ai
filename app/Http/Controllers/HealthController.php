<?php

namespace App\Http\Controllers;

use App\Models\AgentConfig;
use App\Services\Exports\RarExportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HealthController extends Controller
{
    /**
     * Liveness probe.
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe checking all essential subsystems.
     */
    public function readiness(RarExportService $rarService): JsonResponse
    {
        $checks = [];
        $isHealthy = true;

        // 1. Database
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok', 'message' => 'Koneksi database berfungsi'];
        } catch (Exception $e) {
            $isHealthy = false;
            $checks['database'] = ['status' => 'error', 'message' => 'Gagal terhubung ke database'];
        }

        // 2. Cache
        try {
            Cache::put('health_check_ping', 'pong', 10);
            $cached = Cache::get('health_check_ping');
            $checks['cache'] = ($cached === 'pong') 
                ? ['status' => 'ok', 'message' => 'Koneksi cache/redis berfungsi']
                : ['status' => 'error', 'message' => 'Cache write/read tidak konsisten'];
        } catch (Exception $e) {
            $isHealthy = false;
            $checks['cache'] = ['status' => 'error', 'message' => 'Gagal mengakses cache'];
        }

        // 3. Storage
        try {
            $testDir = storage_path('app/workspaces');
            if (!File::exists($testDir)) {
                File::makeDirectory($testDir, 0755, true);
            }
            $testFile = $testDir . '/health_test.tmp';
            File::put($testFile, 'test');
            File::delete($testFile);
            $checks['storage'] = ['status' => 'ok', 'message' => 'Direktori storage workspace dapat ditulisi'];
        } catch (Exception $e) {
            $isHealthy = false;
            $checks['storage'] = ['status' => 'error', 'message' => 'Storage tidak dapat ditulisi'];
        }

        // 4. TokenomixAI Provider Status
        $agentConfig = AgentConfig::first();
        $checks['tokenomix_provider'] = [
            'status' => (!empty($agentConfig?->api_key_encrypted)) ? 'configured' : 'needs_key',
            'is_active' => (bool) ($agentConfig?->is_active ?? false),
            'last_test_status' => $agentConfig?->last_test_status ?? 'untested',
        ];

        // 5. RAR Dependency Readiness
        $rarStatus = $rarService->getReadinessStatus();
        $checks['rar_archiver'] = [
            'status' => $rarStatus['is_ready'] ? 'ready' : 'not_installed',
            'message' => $rarStatus['message'],
        ];

        $httpCode = $isHealthy ? 200 : 503;

        return response()->json([
            'status' => $isHealthy ? 'ready' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $httpCode);
    }
}
