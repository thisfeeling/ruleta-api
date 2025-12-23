<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Public\StatusController;
use App\Http\Controllers\Auth\{AuthController, PlayerAuthController};

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Status API - Public health check
// Health check endpoint - a small, lightweight controller so monitoring and LB checks can hit it.
Route::get('/status', [StatusController::class, 'index'])->name('status');

// Public Auth routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/player/join', [PlayerAuthController::class, 'join']);
Route::post('/auth/player/reconnect', [PlayerAuthController::class, 'reconnect']);

// Protected routes
Route::middleware(['auth:sanctum', 'sanctum.token_expired'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Supervisor only routes
    Route::middleware(['supervisor'])->prefix('supervisor')->group(function () {
        Route::post('/shows/{show}/start', [\App\Http\Controllers\Supervisor\ShowController::class, 'start']);
        Route::post('/shows/{show}/pause', [\App\Http\Controllers\Supervisor\ShowController::class, 'pause']);
        Route::post('/shows/{show}/end', [\App\Http\Controllers\Supervisor\ShowController::class, 'end']);

        // Audio review endpoints
        Route::post('/audio/{audioPlay}/approve', [\App\Http\Controllers\Supervisor\AudioController::class, 'approve']);
        Route::post('/audio/{audioPlay}/reject', [\App\Http\Controllers\Supervisor\AudioController::class, 'reject']);
    });

    // Game endpoints (player actions, state, supervisor complete)
    Route::post('/games/{game}/action', [\App\Http\Controllers\GameController::class, 'action']);
    Route::get('/games/{game}/state', [\App\Http\Controllers\GameController::class, 'state']);
    Route::post('/games/{game}/complete', [\App\Http\Controllers\GameController::class, 'complete'])->middleware('supervisor');

    // Achievements
    Route::get('/achievements', [\App\Http\Controllers\AchievementController::class, 'all']);
    Route::get('/achievements/me', [\App\Http\Controllers\AchievementController::class, 'me']);
});
