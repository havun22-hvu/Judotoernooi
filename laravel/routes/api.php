<?php

use App\Http\Controllers\Api\ToernooiApiController;
use App\Http\Controllers\LocalSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public API endpoints
    Route::get('toernooi/actief', [ToernooiApiController::class, 'actief']);

    // Toernooi specific endpoints
    Route::prefix('toernooi/{toernooi}')->group(function () {
        Route::get('statistieken', [ToernooiApiController::class, 'statistieken']);
        Route::get('blokken', [ToernooiApiController::class, 'blokken']);
        Route::get('matten', [ToernooiApiController::class, 'matten']);
    });
});

/*
|--------------------------------------------------------------------------
| Local Sync API Routes (for local server sync)
|--------------------------------------------------------------------------
*/
Route::prefix('local-sync')->group(function () {
    // Get today's tournaments with all data for local server sync
    Route::get('toernooien-vandaag', [LocalSyncController::class, 'syncData']);

    // Health check for connectivity test
    Route::get('health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));
});

/*
|--------------------------------------------------------------------------
| Sync API Routes (cloud endpoints for receiving local changes)
|--------------------------------------------------------------------------
*/
Route::prefix('sync')->group(function () {
    // Export full tournament data for local sync
    Route::get('export/{toernooi}', [\App\Http\Controllers\Api\SyncApiController::class, 'export']);

    // Receive changes from local server
    Route::post('receive', [\App\Http\Controllers\Api\SyncApiController::class, 'receive']);
});

// Health check endpoint
Route::get('health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));
