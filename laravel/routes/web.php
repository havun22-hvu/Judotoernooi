<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlokController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\CoachPortalController;
use App\Http\Controllers\JudokaController;
use App\Http\Controllers\MatController;
use App\Http\Controllers\PouleController;
use App\Http\Controllers\ToernooiController;
use App\Http\Controllers\WeegkaartController;
use App\Http\Controllers\WegingController;
use App\Http\Middleware\CheckToernooiRol;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Homepage - redirect to dashboard
Route::get('/', fn() => redirect()->route('dashboard'));

// Dashboard
Route::get('/dashboard', [ToernooiController::class, 'dashboard'])->name('dashboard');

// Toernooi management
Route::resource('toernooi', ToernooiController::class);
Route::put('toernooi/{toernooi}/wachtwoorden', [ToernooiController::class, 'updateWachtwoorden'])->name('toernooi.wachtwoorden');

// Toernooi sub-routes
Route::prefix('toernooi/{toernooi}')->name('toernooi.')->group(function () {
    // Auth routes (public)
    Route::get('login', [AuthController::class, 'loginForm'])->name('auth.login');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login.post');
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Admin only routes
    Route::middleware(CheckToernooiRol::class . ':admin')->group(function () {
        // Judokas management (zoek route MOET voor resource staan!)
        Route::get('judoka/zoek', [JudokaController::class, 'zoek'])->name('judoka.zoek');
        Route::get('judoka/import', [JudokaController::class, 'importForm'])->name('judoka.import');
        Route::post('judoka/import', [JudokaController::class, 'import'])->name('judoka.import.store');
        Route::post('judoka/valideer', [JudokaController::class, 'valideer'])->name('judoka.valideer');
        Route::patch('judoka/{judoka}/update-api', [JudokaController::class, 'updateApi'])->name('judoka.update-api');
        Route::resource('judoka', JudokaController::class)->except(['create', 'store']);

        // Poules management
        Route::post('poule/genereer', [PouleController::class, 'genereer'])->name('poule.genereer');
        Route::post('poule/verifieer', [PouleController::class, 'verifieer'])->name('poule.verifieer');
        Route::post('poule/verplaats-judoka', [PouleController::class, 'verplaatsJudokaApi'])->name('poule.verplaats-judoka-api');
        Route::get('poule', [PouleController::class, 'index'])->name('poule.index');

        // Blokken management
        Route::post('blok/genereer-verdeling', [BlokController::class, 'genereerVerdeling'])->name('blok.genereer-verdeling');
        Route::post('blok/{blok}/sluit-weging', [BlokController::class, 'sluitWeging'])->name('blok.sluit-weging');
        Route::post('blok/{blok}/genereer-wedstrijdschemas', [BlokController::class, 'genereerWedstrijdschemas'])->name('blok.genereer-wedstrijdschemas');
        Route::resource('blok', BlokController::class)->only(['index', 'show']);

        // Clubs management
        Route::get('club', [ClubController::class, 'index'])->name('club.index');
        Route::post('club', [ClubController::class, 'store'])->name('club.store');
        Route::put('club/{club}', [ClubController::class, 'update'])->name('club.update');
        Route::delete('club/{club}', [ClubController::class, 'destroy'])->name('club.destroy');
        Route::post('club/{club}/verstuur', [ClubController::class, 'verstuurUitnodiging'])->name('club.verstuur');
        Route::post('club/verstuur-alle', [ClubController::class, 'verstuurAlleUitnodigingen'])->name('club.verstuur-alle');
    });

    // Jury + Admin routes (zaaloverzicht)
    Route::middleware(CheckToernooiRol::class . ':jury')->group(function () {
        Route::get('blok/zaaloverzicht', [BlokController::class, 'zaaloverzicht'])->name('blok.zaaloverzicht');
        Route::get('poule/{poule}/wedstrijdschema', [PouleController::class, 'wedstrijdschema'])->name('poule.wedstrijdschema');
    });

    // Weging routes (weging + admin)
    Route::middleware(CheckToernooiRol::class . ':weging')->group(function () {
        Route::get('weging', [WegingController::class, 'index'])->name('weging.index');
        Route::get('weging/interface', [WegingController::class, 'interface'])->name('weging.interface');
        Route::get('weging/blok/{blok}', [WegingController::class, 'index'])->name('weging.blok');
        Route::post('weging/{judoka}/registreer', [WegingController::class, 'registreer'])->name('weging.registreer');
        Route::post('weging/{judoka}/aanwezig', [WegingController::class, 'markeerAanwezig'])->name('weging.aanwezig');
        Route::post('weging/{judoka}/afwezig', [WegingController::class, 'markeerAfwezig'])->name('weging.afwezig');
        Route::post('weging/scan-qr', [WegingController::class, 'scanQR'])->name('weging.scan-qr');
    });

    // Mat routes (mat + admin)
    Route::middleware(CheckToernooiRol::class . ':mat')->group(function () {
        Route::get('mat', [MatController::class, 'index'])->name('mat.index');
        Route::get('mat/interface', [MatController::class, 'interface'])->name('mat.interface');
        Route::get('mat/{mat}/{blok?}', [MatController::class, 'show'])->name('mat.show');
        Route::post('mat/wedstrijden', [MatController::class, 'getWedstrijden'])->name('mat.wedstrijden');
        Route::post('mat/uitslag', [MatController::class, 'registreerUitslag'])->name('mat.uitslag');
    });

    // Spreker routes (spreker + admin)
    Route::middleware(CheckToernooiRol::class . ':spreker')->group(function () {
        Route::get('spreker', [BlokController::class, 'sprekerInterface'])->name('spreker.interface');
    });

});

// Coach Portal (public routes with token authentication)
Route::prefix('coach')->name('coach.')->group(function () {
    Route::get('{token}', [CoachPortalController::class, 'index'])->name('portal');
    Route::post('{token}/login', [CoachPortalController::class, 'login'])->name('login');
    Route::post('{token}/registreer', [CoachPortalController::class, 'registreer'])->name('registreer');
    Route::post('{token}/logout', [CoachPortalController::class, 'logout'])->name('logout');
    Route::get('{token}/judokas', [CoachPortalController::class, 'judokas'])->name('judokas');
    Route::post('{token}/judoka', [CoachPortalController::class, 'storeJudoka'])->name('judoka.store');
    Route::put('{token}/judoka/{judoka}', [CoachPortalController::class, 'updateJudoka'])->name('judoka.update');
    Route::delete('{token}/judoka/{judoka}', [CoachPortalController::class, 'destroyJudoka'])->name('judoka.destroy');
    Route::get('{token}/weegkaarten', [CoachPortalController::class, 'weegkaarten'])->name('weegkaarten');
});

// Weegkaart (public, accessed via QR code)
Route::get('weegkaart/{token}', [WeegkaartController::class, 'show'])->name('weegkaart.show');
