<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlokController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\CoachPortalController;
use App\Http\Controllers\JudokaController;
use App\Http\Controllers\MatController;
use App\Http\Controllers\OrganisatorAuthController;
use App\Http\Controllers\PouleController;
use App\Http\Controllers\RoleToegang;
use App\Http\Controllers\ToernooiController;
use App\Http\Controllers\WeegkaartController;
use App\Http\Controllers\WedstrijddagController;
use App\Http\Controllers\WegingController;
use App\Http\Controllers\CoachKaartController;
use App\Http\Controllers\PubliekController;
use App\Http\Controllers\PaginaBuilderController;
use App\Http\Middleware\CheckToernooiRol;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Homepage
Route::get('/', fn() => view('pages.home'))->name('home');

/*
|--------------------------------------------------------------------------
| Organisator Authentication
|--------------------------------------------------------------------------
*/
Route::prefix('organisator')->name('organisator.')->group(function () {
    // Guest routes
    Route::middleware('guest:organisator')->group(function () {
        Route::get('login', [OrganisatorAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [OrganisatorAuthController::class, 'login'])->name('login.submit');
        Route::get('register', [OrganisatorAuthController::class, 'showRegister'])->name('register');
        Route::post('register', [OrganisatorAuthController::class, 'register'])->name('register.submit');
        Route::get('wachtwoord-vergeten', [OrganisatorAuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('wachtwoord-vergeten', [OrganisatorAuthController::class, 'sendResetLink'])->name('password.email');
        Route::get('wachtwoord-reset/{token}', [OrganisatorAuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('wachtwoord-reset', [OrganisatorAuthController::class, 'resetPassword'])->name('password.update');
    });

    // Authenticated routes
    Route::middleware('auth:organisator')->group(function () {
        Route::post('logout', [OrganisatorAuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [ToernooiController::class, 'organisatorDashboard'])->name('dashboard');
    });
});

// Dashboard (legacy - redirect to organisator dashboard)
Route::get('/dashboard', [ToernooiController::class, 'dashboard'])->name('dashboard');

// Toernooi management
Route::resource('toernooi', ToernooiController::class);
Route::put('toernooi/{toernooi}/wachtwoorden', [ToernooiController::class, 'updateWachtwoorden'])->name('toernooi.wachtwoorden');
Route::put('toernooi/{toernooi}/bloktijden', [ToernooiController::class, 'updateBloktijden'])->name('toernooi.bloktijden');

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
        Route::post('poule', [PouleController::class, 'store'])->name('poule.store');
        Route::patch('poule/{poule}/kruisfinale', [PouleController::class, 'updateKruisfinale'])->name('poule.update-kruisfinale');
        Route::delete('poule/{poule}', [PouleController::class, 'destroy'])->name('poule.destroy');
        Route::get('poule', [PouleController::class, 'index'])->name('poule.index');

        // Blokken management
        Route::post('blok/genereer-verdeling', [BlokController::class, 'genereerVerdeling'])->name('blok.genereer-verdeling');
        Route::post('blok/kies-variant', [BlokController::class, 'kiesVariant'])->name('blok.kies-variant');
        Route::post('blok/reset-verdeling', [BlokController::class, 'resetVerdeling'])->name('blok.reset-verdeling');
        Route::post('blok/verplaats-categorie', [BlokController::class, 'verplaatsCategorie'])->name('blok.verplaats-categorie');
        Route::post('blok/update-gewenst', [BlokController::class, 'updateGewenst'])->name('blok.update-gewenst');
        Route::post('blok/zet-op-mat', [BlokController::class, 'zetOpMat'])->name('blok.zet-op-mat');
        Route::post('blok/{blok}/sluit-weging', [BlokController::class, 'sluitWeging'])->name('blok.sluit-weging');
        Route::post('blok/{blok}/genereer-wedstrijdschemas', [BlokController::class, 'genereerWedstrijdschemas'])->name('blok.genereer-wedstrijdschemas');
        Route::get('blok/zaaloverzicht', [BlokController::class, 'zaaloverzicht'])->name('blok.zaaloverzicht');
        Route::post('blok/activeer-categorie', [BlokController::class, 'activeerCategorie'])->name('blok.activeer-categorie');
        Route::post('blok/verplaats-poule', [BlokController::class, 'verplaatsPoule'])->name('blok.verplaats-poule');
        Route::post('blok/genereer-poule-wedstrijden', [BlokController::class, 'genereerPouleWedstrijden'])->name('blok.genereer-poule-wedstrijden');
        Route::resource('blok', BlokController::class)->only(['index', 'show']);

        // Clubs management
        Route::get('club', [ClubController::class, 'index'])->name('club.index');
        Route::post('club', [ClubController::class, 'store'])->name('club.store');
        Route::put('club/{club}', [ClubController::class, 'update'])->name('club.update');
        Route::delete('club/{club}', [ClubController::class, 'destroy'])->name('club.destroy');
        Route::post('club/{club}/verstuur', [ClubController::class, 'verstuurUitnodiging'])->name('club.verstuur');
        Route::post('club/verstuur-alle', [ClubController::class, 'verstuurAlleUitnodigingen'])->name('club.verstuur-alle');
        Route::get('club/{club}/coach-url', [ClubController::class, 'getCoachUrl'])->name('club.coach-url');

        // Coach management
        Route::post('club/{club}/coach', [ClubController::class, 'storeCoach'])->name('club.coach.store');
        Route::put('coach/{coach}', [ClubController::class, 'updateCoach'])->name('club.coach.update');
        Route::delete('coach/{coach}', [ClubController::class, 'destroyCoach'])->name('club.coach.destroy');
        Route::post('coach/{coach}/regenerate-pin', [ClubController::class, 'regeneratePincode'])->name('club.coach.regenerate-pin');

        // Coach Kaarten (toegang dojo)
        Route::get('coach-kaarten', [CoachKaartController::class, 'index'])->name('coach-kaart.index');
        Route::post('coach-kaarten/genereer', [CoachKaartController::class, 'genereer'])->name('coach-kaart.genereer');

        // Pagina Builder (publieke voorpagina bewerken)
        Route::get('pagina-builder', [PaginaBuilderController::class, 'index'])->name('pagina-builder.index');
        Route::post('pagina-builder/opslaan', [PaginaBuilderController::class, 'opslaan'])->name('pagina-builder.opslaan');
        Route::post('pagina-builder/upload', [PaginaBuilderController::class, 'upload'])->name('pagina-builder.upload');
        Route::delete('pagina-builder/afbeelding', [PaginaBuilderController::class, 'verwijderAfbeelding'])->name('pagina-builder.verwijder-afbeelding');
    });

    // Jury + Admin routes
    Route::middleware(CheckToernooiRol::class . ':jury')->group(function () {
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

    // Wedstrijddag routes (admin only)
    Route::middleware(CheckToernooiRol::class . ':admin')->group(function () {
        Route::get('wedstrijddag/poules', [WedstrijddagController::class, 'poules'])->name('wedstrijddag.poules');
        Route::post('wedstrijddag/verplaats-judoka', [WedstrijddagController::class, 'verplaatsJudoka'])->name('wedstrijddag.verplaats-judoka');
        Route::post('wedstrijddag/naar-zaaloverzicht', [WedstrijddagController::class, 'naarZaaloverzicht'])->name('wedstrijddag.naar-zaaloverzicht');
        Route::post('wedstrijddag/nieuwe-poule', [WedstrijddagController::class, 'nieuwePoule'])->name('wedstrijddag.nieuwe-poule');
        Route::post('wedstrijddag/verwijder-uit-poule', [WedstrijddagController::class, 'verwijderUitPoule'])->name('wedstrijddag.verwijder-uit-poule');
    });

    // Mat routes (mat + admin)
    Route::middleware(CheckToernooiRol::class . ':mat')->group(function () {
        Route::get('mat', [MatController::class, 'index'])->name('mat.index');
        Route::get('mat/interface', [MatController::class, 'interface'])->name('mat.interface');
        Route::get('mat/{mat}/{blok?}', [MatController::class, 'show'])->name('mat.show');
        Route::post('mat/wedstrijden', [MatController::class, 'getWedstrijden'])->name('mat.wedstrijden');
        Route::post('mat/uitslag', [MatController::class, 'registreerUitslag'])->name('mat.uitslag');
        Route::post('mat/poule-klaar', [MatController::class, 'pouleKlaar'])->name('mat.poule-klaar');
        Route::post('mat/genereer-wedstrijden', [MatController::class, 'genereerWedstrijden'])->name('mat.genereer-wedstrijden');
    });

    // Spreker routes (spreker + admin)
    Route::middleware(CheckToernooiRol::class . ':spreker')->group(function () {
        Route::get('spreker', [BlokController::class, 'sprekerInterface'])->name('spreker.interface');
    });

});

// Coach Portal (public routes with token authentication - legacy)
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

// Coach Portal with shared code and PIN (new system)
Route::prefix('school')->name('coach.portal.')->group(function () {
    Route::get('{code}', [CoachPortalController::class, 'indexCode'])->name('code');
    Route::post('{code}/login', [CoachPortalController::class, 'loginPin'])->name('login');
    Route::post('{code}/logout', [CoachPortalController::class, 'logoutCode'])->name('logout');
    Route::get('{code}/judokas', [CoachPortalController::class, 'judokasCode'])->name('judokas');
    Route::post('{code}/judoka', [CoachPortalController::class, 'storeJudokaCode'])->name('judoka.store');
    Route::put('{code}/judoka/{judoka}', [CoachPortalController::class, 'updateJudokaCode'])->name('judoka.update');
    Route::delete('{code}/judoka/{judoka}', [CoachPortalController::class, 'destroyJudokaCode'])->name('judoka.destroy');
    Route::get('{code}/weegkaarten', [CoachPortalController::class, 'weegkaartenCode'])->name('weegkaarten');
    Route::get('{code}/coachkaarten', [CoachPortalController::class, 'coachkaartenCode'])->name('coachkaarten');
    Route::post('{code}/coachkaart/{coachKaart}/toewijzen', [CoachPortalController::class, 'toewijzenCoachkaart'])->name('coachkaart.toewijzen');
});

// Weegkaart (public, accessed via QR code)
Route::get('weegkaart/{token}', [WeegkaartController::class, 'show'])->name('weegkaart.show');

// Coach Kaart (public, accessed via QR code - toegang tot dojo)
Route::get('coach-kaart/{qrCode}', [CoachKaartController::class, 'show'])->name('coach-kaart.show');
Route::get('coach-kaart/{qrCode}/activeer', [CoachKaartController::class, 'activeer'])->name('coach-kaart.activeer');
Route::post('coach-kaart/{qrCode}/activeer', [CoachKaartController::class, 'activeerOpslaan'])->name('coach-kaart.activeer.opslaan');
Route::get('coach-kaart/{qrCode}/scan', [CoachKaartController::class, 'scan'])->name('coach-kaart.scan');

// Role access via secret code (vrijwilligers)
Route::get('team/{code}', [RoleToegang::class, 'access'])->name('rol.toegang');

// Generic role interfaces (session-based, no toernooi in URL)
Route::middleware('rol.sessie')->group(function () {
    Route::get('weging', [RoleToegang::class, 'wegingInterface'])->name('rol.weging');
    Route::get('mat', [RoleToegang::class, 'matInterface'])->name('rol.mat');
    Route::get('mat/{mat}', [RoleToegang::class, 'matShow'])->name('rol.mat.show');
    Route::get('jury', [RoleToegang::class, 'juryInterface'])->name('rol.jury');
    Route::get('spreker', [RoleToegang::class, 'sprekerInterface'])->name('rol.spreker');
    Route::get('dojo', [RoleToegang::class, 'dojoInterface'])->name('rol.dojo');
});

/*
|--------------------------------------------------------------------------
| Public Pages (no authentication required)
| IMPORTANT: These routes must be LAST to avoid conflicts with other routes
|--------------------------------------------------------------------------
*/
// Short public URL: /toernooi-naam
Route::get('/{toernooi}', [PubliekController::class, 'index'])
    ->name('publiek.index')
    ->where('toernooi', '^(?!admin|login|logout|organisator|toernooi|coach|team|weging|mat|jury|spreker|dojo|weegkaart|coach-kaart).*$');

Route::post('/{toernooi}/favorieten', [PubliekController::class, 'favorieten'])
    ->name('publiek.favorieten')
    ->where('toernooi', '^(?!admin|login|logout|organisator|toernooi|coach|team|weging|mat|jury|spreker|dojo|weegkaart|coach-kaart).*$');

Route::get('/{toernooi}/zoeken', [PubliekController::class, 'zoeken'])
    ->name('publiek.zoeken')
    ->where('toernooi', '^(?!admin|login|logout|organisator|toernooi|coach|team|weging|mat|jury|spreker|dojo|weegkaart|coach-kaart).*$');
