<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Public\StatusController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Status API - Public health check
// Health check endpoint - a small, lightweight controller so monitoring and LB checks can hit it.
Route::get('/status', [StatusController::class, 'index'])->name('status');
