<?php

use App\Http\Controllers\Api\PreviewController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Liveness and readiness endpoints
Route::get('/health', [HealthController::class, 'liveness']);
Route::get('/health/ready', [HealthController::class, 'readiness']);

// Isolated Sandboxed Preview endpoint
Route::get('/preview/{projectId}/{token}', [PreviewController::class, 'render']);

// Single Page Application entrypoint
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
