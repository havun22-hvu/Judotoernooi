<?php

use App\Http\Controllers\Api\ClubSyncController;
use App\Http\Controllers\Api\ScoreboardController;
use App\Http\Controllers\Api\SchoolPortalController;
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

// Public: receive error reports from the app (no auth — app may send before login)
Route::post('/scoreboard/error-report', [ScoreboardController::class, 'errorReport'])
    ->middleware('throttle:60,1')
    ->name('api.scoreboard.error-report');

// Public: authenticate with the 12-character role code to receive a Bearer token.
// The `throttle:login` middleware mitigates brute-force of the code itself.
Route::post('/scoreboard/auth', [ScoreboardController::class, 'auth'])
    ->middleware('throttle:login')
    ->name('api.scoreboard.auth');

// Protected: require valid Bearer token. Throttled per token (see the 'scoreboard'
// limiter in bootstrap/app.php) so one misbehaving device cannot hammer the API.
Route::middleware(['scoreboard.token', 'throttle:scoreboard'])->prefix('scoreboard')->name('api.scoreboard.')->group(function () {
    Route::get('/current-match', [ScoreboardController::class, 'currentMatch'])->name('current-match');
    Route::get('/green-check', [ScoreboardController::class, 'greenCheck'])->name('green-check');
    Route::post('/result', [ScoreboardController::class, 'result'])->name('result');
    Route::post('/event', [ScoreboardController::class, 'event'])->name('event');
    Route::post('/heartbeat', [ScoreboardController::class, 'heartbeat'])->name('heartbeat');
    Route::post('/tv-link', [ScoreboardController::class, 'tvLink'])->name('tv-link');
});

/*
|--------------------------------------------------------------------------
| HavunClub integration API
|--------------------------------------------------------------------------
| HavunClub (the hub) pushes stamdata + entries and pulls results.
| Auth via a per-Organisator Bearer token (club.token middleware) — the token
| identifies the tenant, so no tenant parameter is sent. Additive: solo
| JudoToernooi is unaffected. Contract: havuncore docs/kb/contracts/havunclub-koppelingen.md
*/
Route::middleware(['club.token', 'throttle:api'])->name('api.club.')->group(function () {
    Route::post('/judokas', [ClubSyncController::class, 'upsertJudoka'])->name('judokas.upsert');
    Route::post('/inschrijvingen', [ClubSyncController::class, 'inschrijven'])->name('inschrijvingen.store');
    Route::get('/toernooien/{toernooi}/resultaten', [ClubSyncController::class, 'resultaten'])->name('resultaten');
    Route::get('/toernooien/{toernooi}/weegkaart/{judoka}', [ClubSyncController::class, 'weegkaart'])->name('weegkaart');
});

/*
|--------------------------------------------------------------------------
| HavunClub school-portal fill API (integration scenario 2)
|--------------------------------------------------------------------------
| A judoschool invited to another organiser's tournament fills its portal from
| HavunClub, authorised by the per-tournament portal code + 5-digit PIN (not the
| ClubApiToken). Auth happens inside the controller; throttle:api caps abuse.
*/
Route::middleware('throttle:api')->name('api.school-portal.')->group(function () {
    Route::post('/school-portal/{code}/inschrijvingen', [SchoolPortalController::class, 'inschrijven'])->name('inschrijven');
});
