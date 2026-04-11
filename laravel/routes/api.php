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

// Public: app version check (no auth needed)
Route::get('/scoreboard/version', function () {
    return response()->json([
        'version' => config('scoreboard.version', '1.0.0'),
        'versionCode' => (int) config('scoreboard.version_code', 100),
        'downloadUrl' => config('scoreboard.download_url', ''),
        'forceUpdate' => (bool) config('scoreboard.force_update', false),
        'releaseNotes' => config('scoreboard.release_notes', ''),
    ]);
})->name('api.scoreboard.version');

// Public: authenticate with the 12-character role code to receive a Bearer token.
// The `throttle:login` middleware mitigates brute-force of the code itself.
Route::post('/scoreboard/auth', [ScoreboardController::class, 'auth'])
    ->middleware('throttle:login')
    ->name('api.scoreboard.auth');

// Protected: require valid Bearer token
Route::middleware('scoreboard.token')->prefix('scoreboard')->name('api.scoreboard.')->group(function () {
    Route::get('/current-match', [ScoreboardController::class, 'currentMatch'])->name('current-match');
    Route::post('/result', [ScoreboardController::class, 'result'])->name('result');
    Route::post('/event', [ScoreboardController::class, 'event'])->name('event');
    Route::post('/heartbeat', [ScoreboardController::class, 'heartbeat'])->name('heartbeat');
});
