<?php

use App\Http\Controllers\Api\ScoreboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Scoreboard API Routes
|--------------------------------------------------------------------------
|
| Stateless API endpoints for the JudoScoreboard Android app.
| Authentication via Bearer token (CheckScoreboardToken middleware).
| No CSRF, no session — pure JSON API.
|
*/

// Public: authenticate with code + pincode to receive Bearer token
Route::post('/scoreboard/auth', [ScoreboardController::class, 'auth'])
    ->middleware('throttle:login')
    ->name('api.scoreboard.auth');

// Protected: require valid Bearer token
Route::middleware('scoreboard.token')->prefix('scoreboard')->name('api.scoreboard.')->group(function () {
    Route::get('/current-match', [ScoreboardController::class, 'currentMatch'])->name('current-match');
    Route::post('/result', [ScoreboardController::class, 'result'])->name('result');
    Route::post('/state', [ScoreboardController::class, 'state'])->name('state');
    Route::post('/heartbeat', [ScoreboardController::class, 'heartbeat'])->name('heartbeat');
});
