<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agent\ModelCatalogService;
use Illuminate\Http\JsonResponse;

class ModelController extends Controller
{
    /**
     * Models the user can choose in chat (live TokenomixAI catalog, GET /v1/models).
     */
    public function index(ModelCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->catalog());
    }
}
