<?php

use App\Http\Controllers\Api\ToernooiApiController;
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
