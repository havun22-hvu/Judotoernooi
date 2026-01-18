<?php

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
use App\Http\Controllers\NoodplanController;
use App\Http\Controllers\PaginaBuilderController;
use App\Http\Controllers\MollieController;
use App\Http\Controllers\DeviceToegangController;
use App\Http\Controllers\DeviceToegangBeheerController;
use App\Http\Controllers\GewichtsklassenPresetController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ReverbController;
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
        Route::post('pin-login', [OrganisatorAuthController::class, 'pinLogin'])->name('pin-login');
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

        // Gewichtsklassen presets
        Route::get('presets', [GewichtsklassenPresetController::class, 'index'])->name('presets.index');
        Route::post('presets', [GewichtsklassenPresetController::class, 'store'])->name('presets.store');
        Route::delete('presets/{preset}', [GewichtsklassenPresetController::class, 'destroy'])->name('presets.destroy');
    });
});

// Dashboard - redirect to organisator dashboard (toernooi selection)
Route::get('/dashboard', function() { return redirect('/organisator/dashboard'); })->middleware('auth:organisator');

// Toernooi management - protected routes (require organisator login)
Route::middleware('auth:organisator')->group(function () {
    Route::get('toernooi', [ToernooiController::class, 'index'])->name('toernooi.index');
    Route::get('toernooi/create', [ToernooiController::class, 'create'])->name('toernooi.create');
    Route::post('toernooi', [ToernooiController::class, 'store'])->name('toernooi.store');
    Route::delete('toernooi/{toernooi}', [ToernooiController::class, 'destroy'])->name('toernooi.destroy');
});

// Toernooi management - public routes (accessible via role password)
Route::get('toernooi/{toernooi}', [ToernooiController::class, 'show'])->name('toernooi.show');
Route::get('toernooi/{toernooi}/edit', [ToernooiController::class, 'edit'])->name('toernooi.edit');
Route::put('toernooi/{toernooi}', [ToernooiController::class, 'update'])->name('toernooi.update');
Route::put('toernooi/{toernooi}/wachtwoorden', [ToernooiController::class, 'updateWachtwoorden'])->name('toernooi.wachtwoorden');
Route::put('toernooi/{toernooi}/bloktijden', [ToernooiController::class, 'updateBloktijden'])->name('toernooi.bloktijden');
Route::put('toernooi/{toernooi}/betalingen', [ToernooiController::class, 'updateBetalingInstellingen'])->name('toernooi.betalingen.instellingen');
Route::post('toernooi/{toernooi}/heropen-voorbereiding', [ToernooiController::class, 'heropenVoorbereiding'])->name('toernooi.heropen-voorbereiding');

// Mollie OAuth & Payments
Route::get('toernooi/{toernooi}/mollie/authorize', [MollieController::class, 'authorize'])->name('mollie.authorize');
Route::get('mollie/callback', [MollieController::class, 'callback'])->name('mollie.callback');
Route::post('toernooi/{toernooi}/mollie/disconnect', [MollieController::class, 'disconnect'])->name('mollie.disconnect');
Route::post('mollie/webhook', [MollieController::class, 'webhook'])->name('mollie.webhook');
Route::get('betaling/simulate', [MollieController::class, 'simulate'])->name('betaling.simulate');
Route::post('betaling/simulate', [MollieController::class, 'simulateComplete'])->name('betaling.simulate.complete');

// Toernooi sub-routes
Route::prefix('toernooi/{toernooi}')->name('toernooi.')->group(function () {
    // Reset route
    Route::post('reset', [ToernooiController::class, 'reset'])->name('reset');

    // Afsluiten routes
    Route::get('afsluiten', [ToernooiController::class, 'afsluiten'])->name('afsluiten');
    Route::post('afsluiten', [ToernooiController::class, 'bevestigAfsluiten'])->name('afsluiten.bevestig');
    Route::post('heropenen', [ToernooiController::class, 'heropenen'])->name('heropenen');

    // Auth routes (public) - redirects naar organisator login
    Route::get('login', fn() => redirect()->route('organisator.login'))->name('auth.login');
    Route::post('login', fn() => redirect()->route('organisator.login')
        ->with('info', 'Gebruik je persoonlijke toegangslink of log in als organisator.'))->name('auth.login.post');
    Route::post('logout', function (\Illuminate\Http\Request $request, \App\Models\Toernooi $toernooi) {
        $request->session()->forget("toernooi_{$toernooi->id}_rol");
        $request->session()->forget("toernooi_{$toernooi->id}_mat");
        return redirect()->route('organisator.login')->with('success', 'Je bent uitgelogd');
    })->name('auth.logout');

    // Device Toegang Beheer API routes (Vrijwilligers)
    Route::middleware(CheckToernooiRol::class . ':admin')->prefix('api/device-toegang')->name('device-toegang.')->group(function () {
        Route::get('/', [DeviceToegangBeheerController::class, 'index'])->name('index');
        Route::post('/', [DeviceToegangBeheerController::class, 'store'])->name('store');
        Route::put('{toegang}', [DeviceToegangBeheerController::class, 'update'])->name('update');
        Route::post('{toegang}/reset', [DeviceToegangBeheerController::class, 'reset'])->name('reset');
        Route::post('{toegang}/regenerate-pin', [DeviceToegangBeheerController::class, 'regeneratePin'])->name('regenerate-pin');
        Route::delete('{toegang}', [DeviceToegangBeheerController::class, 'destroy'])->name('destroy');
        Route::post('reset-all', [DeviceToegangBeheerController::class, 'resetAll'])->name('reset-all');
    });

    // Admin only routes
    Route::middleware(CheckToernooiRol::class . ':admin')->group(function () {
        // Judokas management (zoek route MOET voor resource staan!)
        Route::get('judoka/zoek', [JudokaController::class, 'zoek'])->name('judoka.zoek');
        Route::get('judoka/import', [JudokaController::class, 'importForm'])->name('judoka.import');
        Route::post('judoka/import', [JudokaController::class, 'import'])->name('judoka.import.store');
        Route::post('judoka/import/confirm', [JudokaController::class, 'importConfirm'])->name('judoka.import.confirm');
        Route::post('judoka/valideer', [JudokaController::class, 'valideer'])->name('judoka.valideer');
        Route::patch('judoka/{judoka}/update-api', [JudokaController::class, 'updateApi'])->name('judoka.update-api');
        Route::resource('judoka', JudokaController::class)->except(['create', 'store']);

        // Poules management
        Route::post('poule/genereer', [PouleController::class, 'genereer'])->name('poule.genereer');
        Route::post('poule/verifieer', [PouleController::class, 'verifieer'])->name('poule.verifieer');
        Route::get('poule/zoek-match/{judoka}', [PouleController::class, 'zoekMatch'])->name('poule.zoek-match');
        Route::post('poule/verplaats-judoka', [PouleController::class, 'verplaatsJudokaApi'])->name('poule.verplaats-judoka-api');
        Route::post('poule', [PouleController::class, 'store'])->name('poule.store');
        Route::patch('poule/{poule}/kruisfinale', [PouleController::class, 'updateKruisfinale'])->name('poule.update-kruisfinale');
        Route::delete('poule/{poule}', [PouleController::class, 'destroy'])->name('poule.destroy');
        Route::get('poule', [PouleController::class, 'index'])->name('poule.index');

        // Eliminatie bracket
        Route::get('poule/{poule}/eliminatie', [PouleController::class, 'eliminatie'])->name('poule.eliminatie');
        Route::post('poule/{poule}/eliminatie/genereer', [PouleController::class, 'genereerEliminatie'])->name('poule.eliminatie.genereer');
        Route::post('poule/{poule}/eliminatie/uitslag', [PouleController::class, 'opslaanEliminatieUitslag'])->name('poule.eliminatie.uitslag');
        Route::post('poule/{poule}/eliminatie/seeding', [PouleController::class, 'seedingBGroep'])->name('poule.eliminatie.seeding');
        Route::get('poule/{poule}/eliminatie/b-groep', [PouleController::class, 'getBGroepSeeding'])->name('poule.eliminatie.b-groep');

        // A-groep seeding (swap/move favorieten)
        Route::get('poule/{poule}/eliminatie/seeding-status', [PouleController::class, 'getSeedingStatus'])->name('poule.eliminatie.seeding-status');
        Route::post('poule/{poule}/eliminatie/swap', [PouleController::class, 'swapSeeding'])->name('poule.eliminatie.swap');
        Route::post('poule/{poule}/eliminatie/move', [PouleController::class, 'moveSeeding'])->name('poule.eliminatie.move');
        Route::post('poule/{poule}/eliminatie/herstel-koppelingen', [PouleController::class, 'herstelBKoppelingen'])->name('poule.eliminatie.herstel-koppelingen');
        Route::get('poule/{poule}/eliminatie/diagnose-koppelingen', [PouleController::class, 'diagnoseBKoppelingen'])->name('poule.eliminatie.diagnose-koppelingen');

        // Blokken management
        Route::post('blok/genereer-verdeling', [BlokController::class, 'genereerVerdeling'])->name('blok.genereer-verdeling');
        Route::post('blok/genereer-variabele-verdeling', [BlokController::class, 'genereerVariabeleVerdeling'])->name('blok.genereer-variabele-verdeling');
        Route::post('blok/kies-variant', [BlokController::class, 'kiesVariant'])->name('blok.kies-variant');
        Route::post('blok/verplaats-categorie', [BlokController::class, 'verplaatsCategorie'])->name('blok.verplaats-categorie');
        Route::post('blok/update-gewenst', [BlokController::class, 'updateGewenst'])->name('blok.update-gewenst');
        Route::post('blok/zet-op-mat', [BlokController::class, 'zetOpMat'])->name('blok.zet-op-mat');
        Route::post('blok/{blok}/sluit-weging', [BlokController::class, 'sluitWeging'])->name('blok.sluit-weging');
        Route::get('blok/zaaloverzicht', [BlokController::class, 'zaaloverzicht'])->name('blok.zaaloverzicht');
        Route::post('blok/activeer-categorie', [BlokController::class, 'activeerCategorie'])->name('blok.activeer-categorie');
        Route::post('blok/reset-categorie', [BlokController::class, 'resetCategorie'])->name('blok.reset-categorie');
        Route::post('blok/reset-alles', [BlokController::class, 'resetAlles'])->name('blok.reset-alles');
        Route::post('blok/verplaats-poule', [BlokController::class, 'verplaatsPoule'])->name('blok.verplaats-poule');
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
        Route::post('club/{club}/coachkaart', [ClubController::class, 'addCoachKaart'])->name('club.coachkaart.add');
        Route::delete('club/{club}/coachkaart', [ClubController::class, 'removeCoachKaart'])->name('club.coachkaart.remove');

        // Coach Kaarten (toegang dojo)
        Route::get('coach-kaarten', [CoachKaartController::class, 'index'])->name('coach-kaart.index');
        Route::post('coach-kaarten/genereer', [CoachKaartController::class, 'genereer'])->name('coach-kaart.genereer');

        // Pagina Builder (publieke voorpagina bewerken)
        Route::get('pagina-builder', [PaginaBuilderController::class, 'index'])->name('pagina-builder.index');
        Route::post('pagina-builder/opslaan', [PaginaBuilderController::class, 'opslaan'])->name('pagina-builder.opslaan');
        Route::post('pagina-builder/upload', [PaginaBuilderController::class, 'upload'])->name('pagina-builder.upload');
        Route::delete('pagina-builder/afbeelding', [PaginaBuilderController::class, 'verwijderAfbeelding'])->name('pagina-builder.verwijder-afbeelding');

        // Resultaten overzicht (organisator)
        Route::get('resultaten', [PubliekController::class, 'organisatorResultaten'])->name('resultaten.index');

        // Reverb (chat server) beheer
        Route::prefix('reverb')->name('reverb.')->group(function () {
            Route::get('status', [ReverbController::class, 'status'])->name('status');
            Route::post('start', [ReverbController::class, 'start'])->name('start');
            Route::post('stop', [ReverbController::class, 'stop'])->name('stop');
        });
    });

    // Noodplan routes (admin + jury/hoofdjury + organisator)
    Route::middleware(CheckToernooiRol::class . ':jury')->prefix('noodplan')->name('noodplan.')->group(function () {
        Route::get('/', [NoodplanController::class, 'index'])->name('index');

        // Voor het toernooi (backup)
        Route::get('/poules/{blok?}', [NoodplanController::class, 'printPoules'])->name('poules');
        Route::get('/weeglijst/{blok?}', [NoodplanController::class, 'printWeeglijst'])->name('weeglijst');
        Route::get('/zaaloverzicht', [NoodplanController::class, 'printZaaloverzicht'])->name('zaaloverzicht');
        Route::get('/weegkaarten', [NoodplanController::class, 'printWeegkaarten'])->name('weegkaarten');
        Route::get('/weegkaarten/club/{club}', [NoodplanController::class, 'printWeegkaartenClub'])->name('weegkaarten.club');
        Route::get('/weegkaarten/judoka/{judoka}', [NoodplanController::class, 'printWeegkaart'])->name('weegkaart');
        Route::get('/coachkaarten', [NoodplanController::class, 'printCoachkaarten'])->name('coachkaarten');
        Route::get('/coachkaarten/club/{club}', [NoodplanController::class, 'printCoachkaartenClub'])->name('coachkaarten.club');
        Route::get('/coachkaarten/coach/{coachKaart}', [NoodplanController::class, 'printCoachkaart'])->name('coachkaart');
        Route::get('/leeg-schema/{aantal}', [NoodplanController::class, 'printLeegSchema'])->name('leeg-schema');
        Route::get('/instellingen', [NoodplanController::class, 'printInstellingen'])->name('instellingen');
        Route::get('/contactlijst', [NoodplanController::class, 'printContactlijst'])->name('contactlijst');

        // Tijdens wedstrijd (live)
        Route::get('/wedstrijdschemas/{blok?}', [NoodplanController::class, 'printWedstrijdschemas'])->name('wedstrijdschemas');
        Route::get('/poule/{poule}/schema', [NoodplanController::class, 'printPouleSchema'])->name('poule-schema');

        // Export
        Route::get('/export-poules/{format?}', [NoodplanController::class, 'exportPoules'])->name('export-poules');
    });

    // Jury + Admin routes
    Route::middleware(CheckToernooiRol::class . ':jury')->group(function () {
        Route::get('poule/{poule}/wedstrijdschema', [PouleController::class, 'wedstrijdschema'])->name('poule.wedstrijdschema');
    });

    // Chat API routes (all authenticated roles)
    Route::prefix('api/chat')->name('chat.')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::post('/', [ChatController::class, 'store'])->name('store');
        Route::post('/read', [ChatController::class, 'markAsRead'])->name('read');
        Route::get('/unread', [ChatController::class, 'unreadCount'])->name('unread');
    });

    // Weging routes (weging + admin)
    Route::middleware(CheckToernooiRol::class . ':weging')->group(function () {
        Route::get('weging', [WegingController::class, 'index'])->name('weging.index');
        Route::get('weging/interface', [WegingController::class, 'interface'])->name('weging.interface');
        Route::get('weging/lijst-json', [WegingController::class, 'lijstJson'])->name('weging.lijst-json');
        Route::get('weging/blok/{blok}', [WegingController::class, 'index'])->name('weging.blok');
        Route::post('weging/{judoka}/registreer', [WegingController::class, 'registreer'])->name('weging.registreer');
        Route::post('weging/{judoka}/aanwezig', [WegingController::class, 'markeerAanwezig'])->name('weging.aanwezig');
        Route::post('weging/{judoka}/afwezig', [WegingController::class, 'markeerAfwezig'])->name('weging.afwezig');
        Route::post('weging/scan-qr', [WegingController::class, 'scanQR'])->name('weging.scan-qr');
        // Judoka zoeken voor weging interface
        Route::get('judoka/zoek-weging', [JudokaController::class, 'zoek'])->name('weging.judoka.zoek');
    });

    // Wedstrijddag routes (admin only)
    Route::middleware(CheckToernooiRol::class . ':admin')->group(function () {
        Route::get('wedstrijddag/poules', [WedstrijddagController::class, 'poules'])->name('wedstrijddag.poules');
        Route::post('wedstrijddag/verplaats-judoka', [WedstrijddagController::class, 'verplaatsJudoka'])->name('wedstrijddag.verplaats-judoka');
        Route::post('wedstrijddag/naar-zaaloverzicht', [WedstrijddagController::class, 'naarZaaloverzicht'])->name('wedstrijddag.naar-zaaloverzicht');
        Route::post('wedstrijddag/nieuwe-poule', [WedstrijddagController::class, 'nieuwePoule'])->name('wedstrijddag.nieuwe-poule');
        Route::post('wedstrijddag/verwijder-uit-poule', [WedstrijddagController::class, 'verwijderUitPoule'])->name('wedstrijddag.verwijder-uit-poule');
        Route::post('wedstrijddag/zet-om-naar-poules', [WedstrijddagController::class, 'zetOmNaarPoules'])->name('wedstrijddag.zetOmNaarPoules');
        Route::post('wedstrijddag/naar-wachtruimte', [WedstrijddagController::class, 'naarWachtruimte'])->name('wedstrijddag.naar-wachtruimte');
    });

    // Mat routes (mat + admin)
    Route::middleware(CheckToernooiRol::class . ':mat')->group(function () {
        Route::get('mat', [MatController::class, 'index'])->name('mat.index');
        Route::get('mat/interface', [MatController::class, 'interface'])->name('mat.interface');
        Route::get('mat/{mat}/{blok?}', [MatController::class, 'show'])->name('mat.show');
        Route::post('mat/wedstrijden', [MatController::class, 'getWedstrijden'])->name('mat.wedstrijden');
        Route::post('mat/uitslag', [MatController::class, 'registreerUitslag'])->name('mat.uitslag');
        Route::post('mat/poule-klaar', [MatController::class, 'pouleKlaar'])->name('mat.poule-klaar');
        Route::post('mat/huidige-wedstrijd', [MatController::class, 'setHuidigeWedstrijd'])->name('mat.huidige-wedstrijd');
        Route::post('mat/plaats-judoka', [MatController::class, 'plaatsJudoka'])->name('mat.plaats-judoka');
        Route::post('mat/verwijder-judoka', [MatController::class, 'verwijderJudoka'])->name('mat.verwijder-judoka');
        Route::post('mat/finale-uitslag', [MatController::class, 'finaleUitslag'])->name('mat.finale-uitslag');
        Route::post('mat/genereer-wedstrijden', [MatController::class, 'genereerWedstrijden'])->name('mat.genereer-wedstrijden');
    });

    // Spreker routes (spreker + admin)
    Route::middleware(CheckToernooiRol::class . ':spreker')->group(function () {
        Route::get('spreker', [BlokController::class, 'sprekerInterface'])->name('spreker.interface');
        Route::post('spreker/afgeroepen', [BlokController::class, 'markeerAfgeroepen'])->name('spreker.afgeroepen');
        Route::post('spreker/terug', [BlokController::class, 'zetAfgeroepenTerug'])->name('spreker.terug');
        Route::post('spreker/notities', [BlokController::class, 'saveNotities'])->name('spreker.notities.save');
        Route::get('spreker/notities', [BlokController::class, 'getNotities'])->name('spreker.notities.get');
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
    Route::get('{token}/afrekenen', [CoachPortalController::class, 'afrekenen'])->name('afrekenen');
    Route::post('{token}/betalen', [CoachPortalController::class, 'betalen'])->name('betalen');
    Route::get('{token}/betaling/succes', [CoachPortalController::class, 'betalingSucces'])->name('betaling.succes');
    Route::get('{token}/betaling/geannuleerd', [CoachPortalController::class, 'betalingGeannuleerd'])->name('betaling.geannuleerd');
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
    Route::get('{code}/resultaten', [CoachPortalController::class, 'resultatenCode'])->name('resultaten');
    Route::post('{code}/sync', [CoachPortalController::class, 'syncJudokasCode'])->name('sync');
    Route::get('{code}/afrekenen', [CoachPortalController::class, 'afrekenCode'])->name('afrekenen');
    Route::post('{code}/betalen', [CoachPortalController::class, 'betalenCode'])->name('betalen');
    Route::get('{code}/betaling/succes', [CoachPortalController::class, 'betalingSuccesCode'])->name('betaling.succes');
    Route::get('{code}/betaling/geannuleerd', [CoachPortalController::class, 'betalingGeannuleerdCode'])->name('betaling.geannuleerd');
});

// Weegkaart (public, accessed via QR code)
Route::get('weegkaart/{token}', [WeegkaartController::class, 'show'])->name('weegkaart.show');

// Coach Kaart (public, accessed via QR code - toegang tot dojo)
Route::get('coach-kaart/{qrCode}', [CoachKaartController::class, 'show'])->name('coach-kaart.show');
Route::get('coach-kaart/{qrCode}/activeer', [CoachKaartController::class, 'activeer'])->name('coach-kaart.activeer');
Route::post('coach-kaart/{qrCode}/activeer', [CoachKaartController::class, 'activeerOpslaan'])->name('coach-kaart.activeer.opslaan');
Route::get('coach-kaart/{qrCode}/scan', [CoachKaartController::class, 'scan'])->name('coach-kaart.scan');

// Role access via secret code (vrijwilligers) - LEGACY
Route::get('team/{code}', [RoleToegang::class, 'access'])->name('rol.toegang');

// Device binding routes (new system)
Route::prefix('toegang')->name('toegang.')->group(function () {
    Route::get('{code}', [DeviceToegangController::class, 'show'])->name('show');
    Route::post('{code}/verify', [DeviceToegangController::class, 'verify'])->name('verify');
});

// Device-bound interfaces (new system)
Route::middleware('device.binding')->group(function () {
    Route::get('weging/{toegang}', [RoleToegang::class, 'wegingDeviceBound'])->name('weging.interface');
    Route::get('mat/{toegang}', [RoleToegang::class, 'matDeviceBound'])->name('mat.interface');
    Route::get('jury/{toegang}', [RoleToegang::class, 'juryDeviceBound'])->name('jury.interface');
    Route::get('spreker/{toegang}', [RoleToegang::class, 'sprekerDeviceBound'])->name('spreker.interface');
    Route::get('dojo/{toegang}', [RoleToegang::class, 'dojoDeviceBound'])->name('dojo.scanner');
});

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
// Note: zoeken and favorieten must come BEFORE the single slug route
Route::get('/publiek/{toernooi}/zoeken', [PubliekController::class, 'zoeken'])
    ->name('publiek.zoeken');

Route::post('/publiek/{toernooi}/scan-qr', [PubliekController::class, 'scanQR'])
    ->name('publiek.scan-qr');

Route::post('/publiek/{toernooi}/weging/{judoka}/registreer', [PubliekController::class, 'registreerGewicht'])
    ->name('publiek.weging.registreer');

Route::post('/publiek/{toernooi}/favorieten', [PubliekController::class, 'favorieten'])
    ->name('publiek.favorieten');

Route::get('/publiek/{toernooi}/manifest.json', [PubliekController::class, 'manifest'])
    ->name('publiek.manifest');

Route::get('/publiek/{toernooi}/uitslagen.csv', [PubliekController::class, 'exportUitslagen'])
    ->name('publiek.export-uitslagen');

Route::get('/{toernooi}', [PubliekController::class, 'index'])
    ->name('publiek.index')
    ->where('toernooi', '^(?!admin|login|logout|organisator|toernooi|coach|team|weging|mat|jury|spreker|dojo|weegkaart|coach-kaart|publiek).*$');
