<?php

use App\Http\Controllers\BlokController;
use App\Http\Controllers\JudokaController;
use App\Http\Controllers\MatController;
use App\Http\Controllers\PouleController;
use App\Http\Controllers\ToernooiController;
use App\Http\Controllers\WegingController;
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

// Toernooi sub-routes
Route::prefix('toernooi/{toernooi}')->name('toernooi.')->group(function () {
    // Judokas
    Route::get('judoka/import', [JudokaController::class, 'importForm'])->name('judoka.import');
    Route::post('judoka/import', [JudokaController::class, 'import'])->name('judoka.import.store');
    Route::get('judoka/zoek', [JudokaController::class, 'zoek'])->name('judoka.zoek');
    Route::resource('judoka', JudokaController::class)->except(['create', 'store']);

    // Poules
    Route::post('poule/genereer', [PouleController::class, 'genereer'])->name('poule.genereer');
    Route::get('poule/{poule}/wedstrijdschema', [PouleController::class, 'wedstrijdschema'])->name('poule.wedstrijdschema');
    Route::post('poule/{poule}/genereer-wedstrijden', [PouleController::class, 'genereerWedstrijden'])->name('poule.genereer-wedstrijden');
    Route::resource('poule', PouleController::class)->only(['index', 'show']);

    // Blokken
    Route::post('blok/genereer-verdeling', [BlokController::class, 'genereerVerdeling'])->name('blok.genereer-verdeling');
    Route::get('blok/zaaloverzicht', [BlokController::class, 'zaaloverzicht'])->name('blok.zaaloverzicht');
    Route::post('blok/{blok}/sluit-weging', [BlokController::class, 'sluitWeging'])->name('blok.sluit-weging');
    Route::post('blok/{blok}/genereer-wedstrijdschemas', [BlokController::class, 'genereerWedstrijdschemas'])->name('blok.genereer-wedstrijdschemas');
    Route::resource('blok', BlokController::class)->only(['index', 'show']);

    // Weging
    Route::get('weging', [WegingController::class, 'index'])->name('weging.index');
    Route::get('weging/interface', [WegingController::class, 'interface'])->name('weging.interface');
    Route::get('weging/blok/{blok}', [WegingController::class, 'index'])->name('weging.blok');
    Route::post('weging/{judoka}/registreer', [WegingController::class, 'registreer'])->name('weging.registreer');
    Route::post('weging/{judoka}/aanwezig', [WegingController::class, 'markeerAanwezig'])->name('weging.aanwezig');
    Route::post('weging/{judoka}/afwezig', [WegingController::class, 'markeerAfwezig'])->name('weging.afwezig');
    Route::post('weging/scan-qr', [WegingController::class, 'scanQR'])->name('weging.scan-qr');

    // Matten
    Route::get('mat', [MatController::class, 'index'])->name('mat.index');
    Route::get('mat/interface', [MatController::class, 'interface'])->name('mat.interface');
    Route::get('mat/{mat}/{blok?}', [MatController::class, 'show'])->name('mat.show');
    Route::post('mat/wedstrijden', [MatController::class, 'getWedstrijden'])->name('mat.wedstrijden');
    Route::post('mat/uitslag', [MatController::class, 'registreerUitslag'])->name('mat.uitslag');
});
