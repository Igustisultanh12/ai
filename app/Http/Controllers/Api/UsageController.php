<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TokenLedger;
use App\Services\Usage\TokenAccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    protected TokenAccountingService $accounting;

    public function __construct(TokenAccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    /**
     * Get user's own token usage summary and quota status.
     */
    public function userSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->accounting->getUserUsageSummary($user);

        return response()->json($summary);
    }

    /**
     * Get user's paginated token ledger history.
     */
    public function userLedger(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = TokenLedger::where('user_id', $user->id);

        if ($request->has('model') && !empty($request->model)) {
            $query->where('model', $request->model);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $ledger = $query->with('project:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($ledger);
    }
}
