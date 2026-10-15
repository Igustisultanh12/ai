<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\GitHubController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\PreviewController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\VibecodingController;
use Illuminate\Support\Facades\Route;

// Public auth endpoints
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('mfa/verify', [AuthController::class, 'verifyMfa'])->middleware('throttle:10,1');
});

// Protected User Endpoints (Session HttpOnly authenticated)
Route::middleware(['auth:web', 'active', 'mfa'])->group(function () {

    // Auth profile & sessions
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('sessions', [AuthController::class, 'activeSessions']);
        Route::post('sessions/revoke-others', [AuthController::class, 'revokeOtherSessions']);
        Route::post('mfa/setup', [AuthController::class, 'setupMfa']);
        Route::post('mfa/confirm', [AuthController::class, 'confirmMfa']);
        Route::post('mfa/disable', [AuthController::class, 'disableMfa']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'updatePassword']);
    });

    // Projects
    Route::apiResource('projects', ProjectController::class);
    Route::prefix('projects/{project}')->group(function () {
        Route::get('files', [ProjectController::class, 'files']);
        Route::get('file', [ProjectController::class, 'getFile']);
        Route::post('file', [ProjectController::class, 'putFile']);
        Route::delete('file', [ProjectController::class, 'deleteFile']);
        Route::get('checkpoints', [ProjectController::class, 'checkpoints']);
        Route::post('checkpoints/{checkpoint}/restore', [ProjectController::class, 'restoreCheckpoint']);

        // Vibecoding
        Route::post('vibecoding/run', [VibecodingController::class, 'run'])->middleware('throttle:20,1');
        Route::get('vibecoding/runs', [VibecodingController::class, 'runs']);
        Route::get('vibecoding/runs/{run}', [VibecodingController::class, 'getRun']);
        Route::post('vibecoding/runs/{run}/apply', [VibecodingController::class, 'applyProposedPatches']);

        // Preview token
        Route::post('preview/token', [PreviewController::class, 'generateToken']);

        // Exports
        Route::post('export', [ExportController::class, 'createExport'])->middleware('throttle:10,1');
        Route::get('exports', [ExportController::class, 'list']);

        // GitHub push
        Route::post('github/push', [GitHubController::class, 'push'])->middleware('throttle:10,1');
    });

    // Chat model catalog (TokenomixAI GET /v1/models)
    Route::get('models', [ModelController::class, 'index']);

    // Conversations & AI Chat
    Route::apiResource('conversations', ConversationController::class);
    Route::prefix('conversations/{conversation}')->group(function () {
        Route::post('stream', [ConversationController::class, 'streamMessage'])->middleware('throttle:60,1');
        Route::post('stop', [ConversationController::class, 'stopGeneration']);
        Route::post('github/push', [ConversationController::class, 'pushToGitHub'])->middleware('throttle:10,1');
    });

    // Exports readiness & downloads
    Route::get('exports/readiness', [ExportController::class, 'readiness']);
    Route::get('exports/{export}/download', [ExportController::class, 'download']);

    // GitHub Connection
    Route::prefix('github')->group(function () {
        Route::get('status', [GitHubController::class, 'status']);
        Route::post('connect', [GitHubController::class, 'connect']);
        Route::delete('disconnect', [GitHubController::class, 'disconnect']);
        Route::get('repos', [GitHubController::class, 'repos']);
        Route::get('branches', [GitHubController::class, 'branches']);
    });

    // Usage & Quota
    Route::prefix('usage')->group(function () {
        Route::get('summary', [UsageController::class, 'userSummary']);
        Route::get('ledger', [UsageController::class, 'userLedger']);
    });

    // Admin Routes
    Route::prefix('admin')->middleware(['admin'])->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('users', [AdminController::class, 'users']);
        Route::put('users/{id}', [AdminController::class, 'updateUser']);
        Route::get('agent', [AdminController::class, 'getAgentConfig']);
        Route::put('agent', [AdminController::class, 'updateAgentConfig']);
        Route::post('agent/test', [AdminController::class, 'testAgentConnection']);
        Route::post('agent/refresh-models', [AdminController::class, 'refreshModels']);
        Route::put('multipliers/{id}', [AdminController::class, 'updateMultiplier']);
        Route::get('ledgers', [AdminController::class, 'tokenLedgers']);
        Route::get('export-csv', [AdminController::class, 'exportUsageCsv']);
        Route::get('audit-logs', [AdminController::class, 'auditLogs']);
    });
});
