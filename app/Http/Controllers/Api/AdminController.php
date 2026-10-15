<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentConfig;
use App\Models\AuditLog;
use App\Models\ModelMultiplier;
use App\Models\Project;
use App\Models\TokenLedger;
use App\Models\User;
use App\Services\Agent\TokenomixClient;
use App\Services\Auth\AuditService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    protected TokenomixClient $tokenomixClient;
    protected AuditService $auditService;

    public function __construct(TokenomixClient $tokenomixClient, AuditService $auditService)
    {
        $this->tokenomixClient = $tokenomixClient;
        $this->auditService = $auditService;
    }

    /**
     * Admin Overview Dashboard stats.
     */
    public function dashboard(): JsonResponse
    {
        $todayWeighted = (float) TokenLedger::whereDate('created_at', today())->where('status', 'success')->sum('weighted_tokens');
        $monthWeighted = (float) TokenLedger::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->where('status', 'success')->sum('weighted_tokens');
        $totalWeighted = (float) TokenLedger::where('status', 'success')->sum('weighted_tokens');

        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $totalProjects = Project::count();

        // Actual vs Estimated vs Unknown tokens breakdown
        $qualityBreakdown = TokenLedger::select('data_source', DB::raw('SUM(weighted_tokens) as total_weighted'), DB::raw('COUNT(*) as count'))
            ->groupBy('data_source')
            ->get();

        // Top users by usage this month
        $topUsers = User::select('users.id', 'users.name', 'users.email', 'users.daily_token_quota', 'users.monthly_token_quota')
            ->selectRaw('COALESCE(SUM(token_ledgers.weighted_tokens), 0) as month_usage')
            ->leftJoin('token_ledgers', function ($join) {
                $join->on('users.id', '=', 'token_ledgers.user_id')
                    ->whereYear('token_ledgers.created_at', now()->year)
                    ->whereMonth('token_ledgers.created_at', now()->month)
                    ->where('token_ledgers.status', 'success');
            })
            ->groupBy('users.id', 'users.name', 'users.email', 'users.daily_token_quota', 'users.monthly_token_quota')
            ->orderBy('month_usage', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'usage' => [
                'today_weighted' => $todayWeighted,
                'month_weighted' => $monthWeighted,
                'total_weighted' => $totalWeighted,
                'quality_breakdown' => $qualityBreakdown,
            ],
            'stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'total_projects' => $totalProjects,
            ],
            'top_users' => $topUsers,
        ]);
    }

    /**
     * List all users with pagination and search.
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('search') && !empty($request->search)) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('email', 'like', $s);
            });
        }

        if ($request->has('role') && !empty($request->role)) {
            $query->where('role', $request->role);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $users = $query->withCount('projects')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($users);
    }

    /**
     * Update user role, status, quota, or concurrency limit.
     * Prevents demoting or deleting the last active admin.
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $targetUser = User::findOrFail($id);
        $admin = $request->user();

        $validated = $request->validate([
            'role' => ['sometimes', 'required', 'in:admin,user'],
            'status' => ['sometimes', 'required', 'in:active,suspended'],
            'daily_token_quota' => ['sometimes', 'required', 'integer', 'min:0'],
            'monthly_token_quota' => ['sometimes', 'required', 'integer', 'min:0'],
            'concurrency_limit' => ['sometimes', 'required', 'integer', 'min:1', 'max:20'],
        ]);

        // Safety rule: Prevent demoting or suspending the last active administrator
        if ($targetUser->isAdmin()) {
            $isDemoting = isset($validated['role']) && $validated['role'] !== 'admin';
            $isSuspending = isset($validated['status']) && $validated['status'] !== 'active';

            if ($isDemoting || $isSuspending) {
                $otherActiveAdminsCount = User::where('role', 'admin')
                    ->where('status', 'active')
                    ->where('id', '!=', $targetUser->id)
                    ->count();

                if ($otherActiveAdminsCount === 0) {
                    return response()->json([
                        'message' => 'Tindakan ditolak: Anda tidak dapat mengubah peran atau menonaktifkan administrator aktif terakhir.',
                    ], 422);
                }
            }
        }

        $targetUser->update($validated);

        $this->auditService->log('admin.user_updated', $admin, 'User', (string) $targetUser->id, [
            'changes' => $validated,
        ]);

        return response()->json([
            'message' => "Pengguna {$targetUser->name} berhasil diperbarui.",
            'user' => $targetUser->fresh(),
        ]);
    }

    /**
     * Get Agent (TokenomixAI) Configuration.
     */
    public function getAgentConfig(): JsonResponse
    {
        $config = AgentConfig::first();
        $multipliers = ModelMultiplier::orderBy('multiplier', 'asc')->get();

        return response()->json([
            'config' => [
                'id' => $config?->id,
                'name' => $config?->name ?? 'TokenomixAI Primary',
                'provider' => $config?->provider ?? 'tokenomixai',
                'base_url' => $config?->base_url ?? 'https://api.tokenomixai.com/v1',
                'masked_api_key' => $config?->masked_api_key,
                'has_api_key' => !empty($config?->api_key_encrypted),
                'default_model' => $config?->default_model ?? 'glm-5.1',
                'allowed_models' => $config?->allowed_models ?? ['glm-5.1', 'deepseek-v4.1-flash', 'kimi-k2.8', 'auto'],
                'max_output_tokens' => $config?->max_output_tokens ?? 4096,
                'temperature' => $config?->temperature ?? 0.70,
                'timeout_seconds' => $config?->timeout_seconds ?? 60,
                'concurrency_limit' => $config?->concurrency_limit ?? 5,
                'max_iterations' => $config?->max_iterations ?? 5,
                'is_active' => $config?->is_active ?? false,
                'last_tested_at' => $config?->last_tested_at,
                'last_test_status' => $config?->last_test_status,
                'last_test_message' => $config?->last_test_message,
            ],
            'multipliers' => $multipliers,
        ]);
    }

    /**
     * Update Agent configuration.
     * Masked or empty API key preserves the existing secret key.
     */
    public function updateAgentConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url'],
            'api_key' => ['nullable', 'string'],
            'default_model' => ['required', 'string'],
            'allowed_models' => ['required', 'array'],
            'max_output_tokens' => ['required', 'integer', 'min:256', 'max:128000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'timeout_seconds' => ['required', 'integer', 'min:10', 'max:300'],
            'concurrency_limit' => ['required', 'integer', 'min:1', 'max:50'],
            'max_iterations' => ['required', 'integer', 'min:1', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ]);

        // SSRF protection on base_url: reject localhost, 127.0.0.1, private IPs, and cloud metadata
        $host = parse_url($validated['base_url'], PHP_URL_HOST);
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '0.0.0.0', '169.254.169.254', 'metadata.google.internal'])) {
            return response()->json(['message' => 'Alamat URL host tidak diizinkan untuk alasan keamanan SSRF.'], 422);
        }

        $config = AgentConfig::firstOrCreate(['id' => 1]);

        $updateData = [
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'default_model' => $validated['default_model'],
            'allowed_models' => $validated['allowed_models'],
            'max_output_tokens' => $validated['max_output_tokens'],
            'temperature' => $validated['temperature'],
            'timeout_seconds' => $validated['timeout_seconds'],
            'concurrency_limit' => $validated['concurrency_limit'],
            'max_iterations' => $validated['max_iterations'],
            'is_active' => $validated['is_active'],
        ];

        // Only update API key if non-empty string provided (empty = retain existing secret)
        if (!empty($validated['api_key']) && !str_contains($validated['api_key'], '••••')) {
            $updateData['api_key_encrypted'] = trim($validated['api_key']);
        }

        $config->update($updateData);

        $this->auditService->log('admin.agent_config_updated', $request->user(), 'AgentConfig', (string) $config->id, [
            'name' => $config->name,
            'default_model' => $config->default_model,
        ]);

        return response()->json([
            'message' => 'Konfigurasi Agent TokenomixAI berhasil disimpan.',
            'config' => [
                'name' => $config->name,
                'base_url' => $config->base_url,
                'masked_api_key' => $config->masked_api_key,
                'has_api_key' => !empty($config->api_key_encrypted),
                'default_model' => $config->default_model,
                'is_active' => $config->is_active,
            ],
        ]);
    }

    /**
     * Live test connection to TokenomixAI.
     */
    public function testAgentConnection(Request $request): JsonResponse
    {
        $config = AgentConfig::first();
        if (!$config || empty($config->api_key_encrypted)) {
            return response()->json([
                'success' => false,
                'status' => 'not_configured',
                'message' => 'API Key belum dikonfigurasi. Harap simpan API Key terlebih dahulu.',
            ], 422);
        }

        $client = new TokenomixClient($config);
        $result = $client->testConnection();

        $config->update([
            'last_tested_at' => now(),
            'last_test_status' => $result['success'] ? 'success' : 'failed',
            'last_test_message' => $result['message'],
        ]);

        $this->auditService->log('admin.agent_tested', $request->user(), 'AgentConfig', (string) $config->id, [
            'success' => $result['success'],
            'latency_ms' => $result['latency_ms'] ?? 0,
        ]);

        return response()->json($result);
    }

    /**
     * Refresh models catalog from TokenomixAI GET /v1/models.
     */
    public function refreshModels(Request $request): JsonResponse
    {
        $config = AgentConfig::first();
        if (!$config || empty($config->api_key_encrypted)) {
            return response()->json(['message' => 'API Key TokenomixAI belum dikonfigurasi.'], 422);
        }

        try {
            $client = new TokenomixClient($config);
            $models = $client->getModels();

            $updatedCount = 0;
            foreach ($models as $m) {
                $modelId = $m['id'] ?? null;
                if ($modelId) {
                    ModelMultiplier::firstOrCreate(
                        ['model_id' => $modelId],
                        [
                            'multiplier' => 2.0000,
                            'source' => 'TokenomixAI Catalog Sync',
                            'supports_tools' => true,
                            'supports_vision' => false,
                            'description' => 'Sinkronisasi otomatis dari endpoint /v1/models',
                        ]
                    );
                    $updatedCount++;
                }
            }

            return response()->json([
                'message' => "Berhasil menyinkronkan {$updatedCount} model dari TokenomixAI.",
                'models' => ModelMultiplier::all(),
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Gagal menyinkronkan katalog model: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update model multiplier.
     */
    public function updateMultiplier(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'multiplier' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'supports_tools' => ['sometimes', 'boolean'],
            'supports_vision' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $item = ModelMultiplier::findOrFail($id);
        $item->update(array_merge($validated, ['source' => 'Admin Manual Override']));

        $this->auditService->log('admin.multiplier_updated', $request->user(), 'ModelMultiplier', (string) $item->id, [
            'model_id' => $item->model_id,
            'multiplier' => $item->multiplier,
        ]);

        return response()->json([
            'message' => "Multiplier model {$item->model_id} diperbarui menjadi {$item->multiplier}x.",
            'item' => $item,
        ]);
    }

    /**
     * Global paginated Token Ledgers.
     */
    public function tokenLedgers(Request $request): JsonResponse
    {
        $query = TokenLedger::with(['user:id,name,email', 'project:id,name']);

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('model') && !empty($request->model)) {
            $query->where('model', $request->model);
        }

        if ($request->has('data_source') && !empty($request->data_source)) {
            $query->where('data_source', $request->data_source);
        }

        $ledgers = $query->orderBy('created_at', 'desc')->paginate(25);

        return response()->json($ledgers);
    }

    /**
     * Export token usage ledger to CSV.
     */
    public function exportUsageCsv(Request $request): StreamedResponse
    {
        $this->auditService->log('admin.export_csv', $request->user());

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="xzy-token-ledger-' . now()->format('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Waktu', 'Pengguna', 'Email', 'Model', 'Prompt Tokens', 
                'Completion Tokens', 'Raw Tokens', 'Multiplier', 'Weighted Tokens', 
                'Kualitas Data', 'Status', 'Latensi (ms)'
            ]);

            TokenLedger::with('user:id,name,email')
                ->orderBy('created_at', 'desc')
                ->chunk(500, function ($records) use ($handle) {
                    foreach ($records as $r) {
                        fputcsv($handle, [
                            $r->id,
                            $r->created_at->toDateTimeString(),
                            $r->user->name ?? 'N/A',
                            $r->user->email ?? 'N/A',
                            $r->model,
                            $r->prompt_tokens,
                            $r->completion_tokens,
                            $r->raw_tokens,
                            $r->multiplier_snapshot,
                            $r->weighted_tokens,
                            $r->data_source,
                            $r->status,
                            $r->latency_ms ?? 0,
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * List audit logs with pagination.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email');

        if ($request->has('action') && !empty($request->action)) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(25);

        return response()->json($logs);
    }
}
