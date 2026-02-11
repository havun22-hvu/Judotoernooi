<?php

use App\Http\Controllers\BlokController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\HealthController;
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
use App\Http\Controllers\WimpelController;
use App\Http\Controllers\DeviceToegangController;
use App\Http\Controllers\DeviceToegangBeheerController;
use App\Http\Controllers\VrijwilligerController;
use App\Http\Controllers\GewichtsklassenPresetController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ReverbController;
use App\Http\Controllers\ToernooiBetalingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocalSyncController;
use App\Http\Middleware\CheckToernooiRol;
use App\Http\Middleware\CheckFreemiumPrint;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Simple ping for connection status check (no auth required)
Route::get('/ping', fn() => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]))->name('ping');

// Health check endpoints for monitoring
Route::get('/health', [HealthController::class, 'check'])->name('health');
Route::get('/health/detailed', [HealthController::class, 'detailed'])->name('health.detailed');

// Locale switch - context-aware: saves to club, toernooi, or organisator
Route::post('/locale/{locale}', function (\Illuminate\Http\Request $request, string $locale) {
    if (!in_array($locale, config('app.available_locales', ['nl', 'en']))) {
        return redirect()->back();
    }

    // Always save in session
    $request->session()->put('locale', $locale);

    // Save to club if club_id provided (coach portal context)
    if ($clubId = $request->input('club_id')) {
        \App\Models\Club::where('id', $clubId)->update(['locale' => $locale]);
    }

    // Save to toernooi if toernooi_id provided
    if ($toernooiId = $request->input('toernooi_id')) {
        \App\Models\Toernooi::where('id', $toernooiId)->update(['locale' => $locale]);
    }

    // Save to organisator if logged in (and no club/toernooi context)
    if (!$clubId && !$toernooiId && \Illuminate\Support\Facades\Auth::guard('organisator')->check()) {
        \Illuminate\Support\Facades\Auth::guard('organisator')->user()->update(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

// Homepage
Route::get('/', fn() => view('pages.home'))->name('home');

// Help pagina
Route::get('/help', fn() => view('pages.help'))->name('help');

// Legal Pages (public, no auth required)
Route::get('/algemene-voorwaarden', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacyverklaring', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/cookiebeleid', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::get('/disclaimer', [LegalController::class, 'disclaimer'])->name('legal.disclaimer');

/*
|--------------------------------------------------------------------------
| Authentication (root level)
|--------------------------------------------------------------------------
*/
// Login route without middleware - controller handles auth check and corrupt sessions
Route::get('login', [OrganisatorAuthController::class, 'showLogin'])->name('login');
Route::middleware('throttle:login')->group(function () {
    Route::post('login', [OrganisatorAuthController::class, 'login'])->name('login.submit');
    Route::post('pin-login', [OrganisatorAuthController::class, 'pinLogin'])->name('pin-login');
});

// Guest routes (only for users not logged in)
Route::middleware('guest:organisator')->group(function () {
    Route::get('registreren', [OrganisatorAuthController::class, 'showRegister'])->name('register');
    Route::post('registreren', [OrganisatorAuthController::class, 'register'])->name('register.submit');
    Route::get('wachtwoord-vergeten', [OrganisatorAuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('wachtwoord-vergeten', [OrganisatorAuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('wachtwoord-reset/{token}', [OrganisatorAuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('wachtwoord-reset', [OrganisatorAuthController::class, 'resetPassword'])->name('password.update');
});

// Authenticated routes
Route::middleware('auth:organisator')->group(function () {
    Route::post('logout', [OrganisatorAuthController::class, 'logout'])->name('logout');
});

// Alias for organisator.login (used in bootstrap/app.php and controllers)
Route::get('organisator/login', fn() => redirect()->route('login'))->name('organisator.login');

// Legacy auth routes - redirect to new URLs
Route::prefix('organisator')->name('organisator.legacy.')->group(function () {
    Route::get('register', fn() => redirect()->route('register'))->name('register');
    Route::get('dashboard', [ToernooiController::class, 'redirectToOrganisatorDashboard'])->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Sitebeheerder (superadmin only)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:organisator')->group(function () {
    Route::get('admin', [ToernooiController::class, 'index'])->name('admin.index');
    // Legacy redirect
    Route::get('toernooi', fn() => redirect()->route('admin.index'))->name('toernooi.index.legacy');

    // Klantenbeheer (sitebeheerder only)
    Route::get('admin/klanten', [AdminController::class, 'klanten'])->name('admin.klanten');
    Route::get('admin/klanten/{klant}', [AdminController::class, 'editKlant'])->name('admin.klanten.edit');
    Route::put('admin/klanten/{klant}', [AdminController::class, 'updateKlant'])->name('admin.klanten.update');
    Route::delete('admin/klanten/{klant}', [AdminController::class, 'destroyKlant'])->name('admin.klanten.destroy');
});

// Dashboard - redirect to organisator dashboard (new URL structure)
Route::get('/dashboard', [ToernooiController::class, 'redirectToOrganisatorDashboard'])->middleware('auth:organisator');

/*
|--------------------------------------------------------------------------
| Organisator Beheer: /{org}/...
|--------------------------------------------------------------------------
*/
Route::prefix('{organisator}')->middleware('auth:organisator')->group(function () {
    // Dashboard
    Route::get('dashboard', [ToernooiController::class, 'organisatorDashboard'])->name('organisator.dashboard');

    // Club management (organisator level - clubs persist across toernooien)
    Route::get('clubs', [ClubController::class, 'indexOrganisator'])->name('organisator.clubs.index');
    Route::post('clubs', [ClubController::class, 'storeOrganisator'])->name('organisator.clubs.store');
    Route::put('clubs/{club}', [ClubController::class, 'updateOrganisator'])->name('organisator.clubs.update');
    Route::delete('clubs/{club}', [ClubController::class, 'destroyOrganisator'])->name('organisator.clubs.destroy');

    // Templates (organisator level)
    Route::get('templates', [\App\Http\Controllers\ToernooiTemplateController::class, 'index'])->name('organisator.templates.index');
    Route::delete('templates/{template}', [\App\Http\Controllers\ToernooiTemplateController::class, 'destroy'])->name('organisator.templates.destroy');
    Route::get('templates/{template}', [\App\Http\Controllers\ToernooiTemplateController::class, 'show'])->name('organisator.templates.show');

    // Gewichtsklassen presets (organisator level)
    Route::get('presets', [GewichtsklassenPresetController::class, 'index'])->name('organisator.presets.index');
    Route::post('presets', [GewichtsklassenPresetController::class, 'store'])->name('organisator.presets.store');
    Route::delete('presets/{preset}', [GewichtsklassenPresetController::class, 'destroy'])->name('organisator.presets.destroy');

    // Toernooi aanmaken
    Route::get('toernooi/nieuw', [ToernooiController::class, 'create'])->name('toernooi.create');
    Route::post('toernooi', [ToernooiController::class, 'store'])->name('toernooi.store');

    // Organisator instellingen
    Route::get('instellingen', [ToernooiController::class, 'organisatorInstellingen'])->name('organisator.instellingen');
    Route::put('instellingen', [ToernooiController::class, 'organisatorInstellingenUpdate'])->name('organisator.instellingen.update');

    // Wimpeltoernooi (organisator level - persistent across tournaments)
    Route::get('wimpeltoernooi', [WimpelController::class, 'index'])->name('organisator.wimpel.index');
    Route::get('wimpeltoernooi/instellingen', [WimpelController::class, 'instellingen'])->name('organisator.wimpel.instellingen');
    Route::post('wimpeltoernooi/milestones', [WimpelController::class, 'storeMilestone'])->name('organisator.wimpel.milestones.store');
    Route::put('wimpeltoernooi/milestones/{milestone}', [WimpelController::class, 'updateMilestone'])->name('organisator.wimpel.milestones.update');
    Route::delete('wimpeltoernooi/milestones/{milestone}', [WimpelController::class, 'destroyMilestone'])->name('organisator.wimpel.milestones.destroy');
    Route::post('wimpeltoernooi/verwerk-toernooi', [WimpelController::class, 'verwerkToernooi'])->name('organisator.wimpel.verwerk');
    Route::get('wimpeltoernooi/export/{format}', [WimpelController::class, 'export'])->name('organisator.wimpel.export');
    Route::get('wimpeltoernooi/{wimpelJudoka}', [WimpelController::class, 'show'])->name('organisator.wimpel.show');
    Route::post('wimpeltoernooi/{wimpelJudoka}/aanpassen', [WimpelController::class, 'aanpassen'])->name('organisator.wimpel.aanpassen');
    Route::post('wimpeltoernooi/{wimpelJudoka}/bevestig', [WimpelController::class, 'bevestigJudoka'])->name('organisator.wimpel.bevestig');
});

// Mollie webhooks & callbacks (no auth, called by Mollie)
Route::get('mollie/callback', [MollieController::class, 'callback'])->name('mollie.callback');
Route::middleware('throttle:webhook')->group(function () {
    Route::post('mollie/webhook', [MollieController::class, 'webhook'])->name('mollie.webhook');
    Route::post('mollie/webhook/toernooi', [MollieController::class, 'webhookToernooi'])->name('mollie.webhook.toernooi');
});
Route::get('betaling/simulate', [MollieController::class, 'simulate'])->name('betaling.simulate');
Route::post('betaling/simulate', [MollieController::class, 'simulateComplete'])->name('betaling.simulate.complete');

/*
|--------------------------------------------------------------------------
| Toernooi Beheer: /{org}/toernooi/{toernooi}/...
| Auth: Organisator login vereist
|--------------------------------------------------------------------------
*/
Route::prefix('{organisator}/toernooi/{toernooi}')->middleware('auth:organisator')->name('toernooi.')->group(function () {
    // Toernooi basis routes
    Route::get('/', [ToernooiController::class, 'show'])->name('show');
    Route::get('edit', [ToernooiController::class, 'edit'])->name('edit');
    Route::put('/', [ToernooiController::class, 'update'])->name('update');
    Route::delete('/', [ToernooiController::class, 'destroy'])->name('destroy');
    Route::put('wachtwoorden', [ToernooiController::class, 'updateWachtwoorden'])->name('wachtwoorden');
    Route::put('bloktijden', [ToernooiController::class, 'updateBloktijden'])->name('bloktijden');
    Route::put('betalingen', [ToernooiController::class, 'updateBetalingInstellingen'])->name('betalingen.instellingen');
    Route::put('portaal', [ToernooiController::class, 'updatePortaalInstellingen'])->name('portaal.instellingen');
    Route::put('local-server-ips', [ToernooiController::class, 'updateLocalServerIps'])->name('local-server-ips');
    Route::get('detect-my-ip', [ToernooiController::class, 'detectMyIp'])->name('detect-my-ip');
    Route::post('heropen-voorbereiding', [ToernooiController::class, 'heropenVoorbereiding'])->name('heropen-voorbereiding');

    // Mollie OAuth
    Route::get('mollie/authorize', [MollieController::class, 'authorize'])->name('mollie.authorize');
    Route::post('mollie/disconnect', [MollieController::class, 'disconnect'])->name('mollie.disconnect');

    // Upgrade routes (freemium)
    Route::get('upgrade', [ToernooiBetalingController::class, 'showUpgrade'])->name('upgrade');
    Route::post('upgrade/kyc', [ToernooiBetalingController::class, 'saveKyc'])->name('upgrade.kyc');
    Route::post('upgrade', [ToernooiBetalingController::class, 'startPayment'])->name('upgrade.start');
    Route::get('upgrade/succes/{betaling}', [ToernooiBetalingController::class, 'success'])->name('upgrade.succes');
    Route::get('upgrade/geannuleerd', [ToernooiBetalingController::class, 'cancelled'])->name('upgrade.geannuleerd');

    // Template opslaan vanuit toernooi
    Route::post('template', [\App\Http\Controllers\ToernooiTemplateController::class, 'store'])->name('template.store');
    Route::put('template/{template}', [\App\Http\Controllers\ToernooiTemplateController::class, 'update'])->name('template.update');

    // Reset route
    Route::post('reset', [ToernooiController::class, 'reset'])->name('reset');

    // Afsluiten routes
    Route::get('afsluiten', [ToernooiController::class, 'afsluiten'])->name('afsluiten');
    Route::post('afsluiten', [ToernooiController::class, 'bevestigAfsluiten'])->name('afsluiten.bevestig');
    Route::post('heropenen', [ToernooiController::class, 'heropenen'])->name('heropenen');

    // Auth routes (public) - redirects naar login
    Route::get('login', fn() => redirect()->route('login'))->name('auth.login');
    Route::post('login', fn() => redirect()->route('login')
        ->with('info', 'Gebruik je persoonlijke toegangslink of log in als organisator.'))->name('auth.login.post');
    Route::post('logout', function (\Illuminate\Http\Request $request, \App\Models\Toernooi $toernooi) {
        $request->session()->forget("toernooi_{$toernooi->id}_rol");
        $request->session()->forget("toernooi_{$toernooi->id}_mat");
        return redirect()->route('login')->with('success', 'Je bent uitgelogd');
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

    // Vrijwilligers API routes (per organisator, via toernooi context)
    Route::middleware(CheckToernooiRol::class . ':admin')->prefix('api/vrijwilligers')->name('vrijwilligers.')->group(function () {
        Route::get('/', [VrijwilligerController::class, 'index'])->name('index');
        Route::post('/', [VrijwilligerController::class, 'store'])->name('store');
        Route::put('{vrijwilliger}', [VrijwilligerController::class, 'update'])->name('update');
        Route::delete('{vrijwilliger}', [VrijwilligerController::class, 'destroy'])->name('destroy');
    });

    // Admin only routes
    Route::middleware(CheckToernooiRol::class . ':admin')->group(function () {
        // Judokas management (zoek route MOET voor resource staan!)
        Route::get('judoka/zoek', [JudokaController::class, 'zoek'])->name('judoka.zoek');
        Route::get('judoka/import', [JudokaController::class, 'importForm'])->name('judoka.import');
        Route::post('judoka/import', [JudokaController::class, 'import'])->name('judoka.import.store');
        Route::post('judoka/import/confirm', [JudokaController::class, 'importConfirm'])->name('judoka.import.confirm');
        Route::get('judoka/import/progress', [JudokaController::class, 'importProgress'])->name('judoka.import.progress');
        Route::post('judoka/valideer', [JudokaController::class, 'valideer'])->name('judoka.valideer');
        Route::post('judoka', [JudokaController::class, 'store'])->name('judoka.store');
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
        Route::post('blok/einde-voorbereiding', [BlokController::class, 'eindeVoorbereiding'])->name('blok.einde-voorbereiding');
        Route::post('blok/activeer-categorie', [BlokController::class, 'activeerCategorie'])->name('blok.activeer-categorie');
        Route::post('blok/reset-categorie', [BlokController::class, 'resetCategorie'])->name('blok.reset-categorie');
        Route::post('blok/activeer-poule', [BlokController::class, 'activeerPoule'])->name('blok.activeer-poule');
        Route::post('blok/reset-poule', [BlokController::class, 'resetPoule'])->name('blok.reset-poule');
        Route::post('blok/reset-alles', [BlokController::class, 'resetAlles'])->name('blok.reset-alles');
        Route::post('blok/reset-blok', [BlokController::class, 'resetBlok'])->name('blok.reset-blok');
        Route::post('blok/verplaats-poule', [BlokController::class, 'verplaatsPoule'])->name('blok.verplaats-poule');
        Route::resource('blok', BlokController::class)->only(['index', 'show']);

        // Clubs management
        Route::get('club', [ClubController::class, 'index'])->name('club.index');
        Route::post('club/{club}/toggle', [ClubController::class, 'toggleClub'])->name('club.toggle');
        Route::post('club/select-all', [ClubController::class, 'selectAllClubs'])->name('club.select-all');
        Route::post('club/deselect-all', [ClubController::class, 'deselectAllClubs'])->name('club.deselect-all');
        Route::post('club/{club}/verstuur', [ClubController::class, 'verstuurUitnodiging'])->name('club.verstuur');
        Route::post('club/verstuur-alle', [ClubController::class, 'verstuurAlleUitnodigingen'])->name('club.verstuur-alle');
        Route::get('club/{club}/coach-url', [ClubController::class, 'getCoachUrl'])->name('club.coach-url');

        // Email log
        Route::get('email-log', [ClubController::class, 'emailLog'])->name('email-log');

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
        Route::post('coach-kaarten/toggle-incheck', [CoachKaartController::class, 'toggleIncheck'])->name('coach-kaart.toggle-incheck');
        Route::get('coach-kaarten/ingecheckt', [CoachKaartController::class, 'ingecheckteCoaches'])->name('coach-kaart.ingecheckt');
        Route::post('coach-kaarten/{coachKaart}/force-checkout', [CoachKaartController::class, 'forceCheckout'])->name('coach-kaart.force-checkout');

        // Pagina Builder (publieke voorpagina bewerken)
        Route::get('pagina-builder', [PaginaBuilderController::class, 'index'])->name('pagina-builder.index');
        Route::post('pagina-builder/opslaan', [PaginaBuilderController::class, 'opslaan'])->name('pagina-builder.opslaan');
        Route::post('pagina-builder/upload', [PaginaBuilderController::class, 'upload'])->name('pagina-builder.upload');
        Route::delete('pagina-builder/afbeelding', [PaginaBuilderController::class, 'verwijderAfbeelding'])->name('pagina-builder.verwijder-afbeelding');

        // Activiteiten log
        Route::get('activiteiten', [ActivityLogController::class, 'index'])->name('activiteiten');

        // Resultaten overzicht (organisator)
        Route::get('resultaten', [PubliekController::class, 'organisatorResultaten'])->name('resultaten.index');

        // Reverb (chat server) beheer
        Route::prefix('reverb')->name('reverb.')->group(function () {
            Route::get('status', [ReverbController::class, 'status'])->name('status');
            Route::post('start', [ReverbController::class, 'start'])->name('start');
            Route::post('stop', [ReverbController::class, 'stop'])->name('stop');
        });
    });

    // Noodplan routes (admin + jury/hoofdjury + organisator) - free tier has limited access
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
        Route::get('/ingevuld-schemas/{blok?}', [NoodplanController::class, 'printIngevuldSchemas'])->name('ingevuld-schemas');
        Route::get('/live-schemas/{blok?}', [NoodplanController::class, 'printLiveSchemas'])->name('live-schemas');

        // Export
        Route::get('/export-poules/{format?}', [NoodplanController::class, 'exportPoules'])->name('export-poules');

        // Live backup sync (SSE)
        Route::get('/stream', [NoodplanController::class, 'stream'])->name('stream');
        Route::get('/sync-data', [NoodplanController::class, 'syncData'])->name('sync-data');

        // Offline pakket
        Route::get('/offline-pakket', [NoodplanController::class, 'downloadOfflinePakket'])->name('offline-pakket');
        Route::post('/upload-resultaten', [NoodplanController::class, 'uploadOfflineResultaten'])->name('upload-resultaten');
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
        Route::post('wedstrijddag/naar-zaaloverzicht-poule', [WedstrijddagController::class, 'naarZaaloverzichtPoule'])->name('wedstrijddag.naar-zaaloverzicht-poule');
        Route::post('wedstrijddag/nieuwe-poule', [WedstrijddagController::class, 'nieuwePoule'])->name('wedstrijddag.nieuwe-poule');
        Route::post('wedstrijddag/verwijder-uit-poule', [WedstrijddagController::class, 'verwijderUitPoule'])->name('wedstrijddag.verwijder-uit-poule');
        Route::post('wedstrijddag/zet-om-naar-poules', [WedstrijddagController::class, 'zetOmNaarPoules'])->name('wedstrijddag.zetOmNaarPoules');
        Route::post('wedstrijddag/wijzig-poule-type', [WedstrijddagController::class, 'wijzigPouleType'])->name('wedstrijddag.wijzigPouleType');
        Route::post('wedstrijddag/naar-wachtruimte', [WedstrijddagController::class, 'naarWachtruimte'])->name('wedstrijddag.naar-wachtruimte');
        Route::post('wedstrijddag/meld-judoka-af', [WedstrijddagController::class, 'meldJudokaAf'])->name('wedstrijddag.meld-judoka-af');
        Route::post('wedstrijddag/herstel-judoka', [WedstrijddagController::class, 'herstelJudoka'])->name('wedstrijddag.herstel-judoka');
        Route::post('wedstrijddag/nieuwe-judoka', [WedstrijddagController::class, 'nieuweJudoka'])->name('wedstrijddag.nieuwe-judoka');
    });

    // Mat routes (mat + admin)
    Route::middleware(CheckToernooiRol::class . ':mat')->group(function () {
        Route::get('mat', [MatController::class, 'index'])->name('mat.index');
        Route::get('mat/interface', [MatController::class, 'interface'])->name('mat.interface');
        Route::get('mat/scoreboard/{wedstrijd?}', [MatController::class, 'scoreboard'])->name('mat.scoreboard');
        Route::get('mat/{mat}/{blok?}', [MatController::class, 'show'])->name('mat.show');
        Route::post('mat/wedstrijden', [MatController::class, 'getWedstrijden'])->name('mat.wedstrijden');
        Route::post('mat/uitslag', [MatController::class, 'registreerUitslag'])->name('mat.uitslag');
        Route::post('mat/poule-klaar', [MatController::class, 'pouleKlaar'])->name('mat.poule-klaar');
        Route::post('mat/huidige-wedstrijd', [MatController::class, 'setHuidigeWedstrijd'])->name('mat.huidige-wedstrijd');
        Route::post('mat/plaats-judoka', [MatController::class, 'plaatsJudoka'])->name('mat.plaats-judoka');
        Route::post('mat/verwijder-judoka', [MatController::class, 'verwijderJudoka'])->name('mat.verwijder-judoka');
        Route::post('mat/finale-uitslag', [MatController::class, 'finaleUitslag'])->name('mat.finale-uitslag');
        Route::post('mat/genereer-wedstrijden', [MatController::class, 'genereerWedstrijden'])->name('mat.genereer-wedstrijden');
        Route::post('mat/bracket-html', [MatController::class, 'getBracketHtml'])->name('mat.bracket-html');
        Route::post('mat/check-admin-wachtwoord', [MatController::class, 'checkAdminWachtwoord'])->name('mat.check-admin-wachtwoord');
        Route::post('mat/barrage', [BlokController::class, 'maakBarrage'])->name('mat.barrage');
    });

    // Spreker routes (spreker + admin)
    Route::middleware(CheckToernooiRol::class . ':spreker')->group(function () {
        Route::get('spreker', [BlokController::class, 'sprekerInterface'])->name('spreker.interface');
        Route::post('spreker/afgeroepen', [BlokController::class, 'markeerAfgeroepen'])->name('spreker.afgeroepen');
        Route::post('spreker/terug', [BlokController::class, 'zetAfgeroepenTerug'])->name('spreker.terug');
        Route::post('spreker/notities', [BlokController::class, 'saveNotities'])->name('spreker.notities.save');
        Route::get('spreker/notities', [BlokController::class, 'getNotities'])->name('spreker.notities.get');
        Route::post('spreker/standings', [BlokController::class, 'getPouleStandings'])->name('spreker.standings');
        Route::post('spreker/wimpel-uitgereikt', [BlokController::class, 'wimpelUitgereikt'])->name('spreker.wimpel-uitgereikt');
    });

});

// Coach Portal with code and PIN - NEW URL structure: /{org}/{toernooi}/school/{code}
Route::prefix('{organisator}/{toernooi}/school')->name('coach.portal.')->group(function () {
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

// Legacy coach portal routes - redirect to new URL structure
Route::prefix('school')->name('coach.portal.legacy.')->group(function () {
    Route::get('{code}', [CoachPortalController::class, 'redirectLegacy'])->name('code');
});

// Weegkaart (public, accessed via QR code)
Route::get('weegkaart/{token}', [WeegkaartController::class, 'show'])->name('weegkaart.show');

// Coach Kaart (public, accessed via QR code - toegang tot dojo)
Route::get('coach-kaart/{qrCode}', [CoachKaartController::class, 'show'])->name('coach-kaart.show');
Route::get('coach-kaart/{qrCode}/activeer', [CoachKaartController::class, 'activeer'])->name('coach-kaart.activeer');
Route::post('coach-kaart/{qrCode}/activeer', [CoachKaartController::class, 'activeerOpslaan'])->name('coach-kaart.activeer.opslaan');
Route::get('coach-kaart/{qrCode}/scan', [CoachKaartController::class, 'scan'])->name('coach-kaart.scan');
Route::post('coach-kaart/{qrCode}/checkin', [CoachKaartController::class, 'checkin'])->name('coach-kaart.checkin');
Route::post('coach-kaart/{qrCode}/checkout', [CoachKaartController::class, 'checkout'])->name('coach-kaart.checkout');
Route::get('coach-kaart/{qrCode}/geschiedenis', [CoachKaartController::class, 'geschiedenis'])->name('coach-kaart.geschiedenis');

// Dojo overzicht API (voor dojo scanner tab) - NEW URL structure
Route::get('{organisator}/{toernooi}/dojo/clubs', [CoachKaartController::class, 'dojoClubs'])->name('dojo.clubs');
Route::get('{organisator}/{toernooi}/dojo/club/{club}', [CoachKaartController::class, 'dojoClubDetail'])->name('dojo.club.detail');

// Role access via secret code (vrijwilligers) - LEGACY
Route::get('team/{code}', [RoleToegang::class, 'access'])->name('rol.toegang');

// Device binding routes - NEW URL structure: /{org}/{toernooi}/toegang/{code}
Route::prefix('{organisator}/{toernooi}')->group(function () {
    Route::prefix('toegang')->name('toegang.')->group(function () {
        Route::get('{code}', [DeviceToegangController::class, 'show'])->name('show');
        Route::post('{code}/verify', [DeviceToegangController::class, 'verify'])->name('verify');
    });

    // Device-bound interfaces
    Route::middleware('device.binding')->group(function () {
        Route::get('weging/{toegang}', [RoleToegang::class, 'wegingDeviceBound'])->name('weging.interface');
        Route::get('mat/{toegang}', [RoleToegang::class, 'matDeviceBound'])->name('mat.interface');
        Route::get('mat/{toegang}/{mat}', [RoleToegang::class, 'matShowDeviceBound'])->name('mat.show');
        Route::post('mat/{toegang}/wedstrijden', [MatController::class, 'getWedstrijdenDevice'])->name('mat.wedstrijden.device');
        Route::post('mat/{toegang}/uitslag', [MatController::class, 'registreerUitslagDevice'])->name('mat.uitslag.device');
        Route::post('mat/{toegang}/huidige-wedstrijd', [MatController::class, 'setHuidigeWedstrijdDevice'])->name('mat.huidige-wedstrijd.device');
        Route::post('mat/{toegang}/poule-klaar', [MatController::class, 'pouleKlaarDevice'])->name('mat.poule-klaar.device');
        Route::post('mat/{toegang}/bracket-html', [MatController::class, 'getBracketHtmlDevice'])->name('mat.bracket-html.device');
        Route::post('mat/{toegang}/check-admin-wachtwoord', [MatController::class, 'checkAdminWachtwoordDevice'])->name('mat.check-admin-wachtwoord.device');
        Route::get('jury/{toegang}', [RoleToegang::class, 'juryDeviceBound'])->name('jury.interface');
        Route::get('spreker/{toegang}', [RoleToegang::class, 'sprekerDeviceBound'])->name('spreker.interface');
        Route::post('spreker/{toegang}/notities', [RoleToegang::class, 'sprekerNotitiesSave'])->name('spreker.notities.save');
        Route::get('spreker/{toegang}/notities', [RoleToegang::class, 'sprekerNotitiesGet'])->name('spreker.notities.get');
        Route::post('spreker/{toegang}/afgeroepen', [RoleToegang::class, 'sprekerAfgeroepen'])->name('spreker.afgeroepen');
        Route::post('spreker/{toegang}/terug', [RoleToegang::class, 'sprekerTerug'])->name('spreker.terug');
        Route::post('spreker/{toegang}/standings', [RoleToegang::class, 'sprekerStandings'])->name('spreker.standings');
        Route::post('spreker/{toegang}/wimpel-uitgereikt', [RoleToegang::class, 'sprekerWimpelUitgereikt'])->name('spreker.wimpel-uitgereikt');
        Route::get('dojo/{toegang}', [RoleToegang::class, 'dojoDeviceBound'])->name('dojo.scanner');
    });
});

// Legacy routes - redirect to new URL structure
Route::get('toegang/{code}', [DeviceToegangController::class, 'redirectToNew'])->name('toegang.legacy');
Route::get('weging/{toegang}', fn($toegang) => app(DeviceToegangController::class)->redirectInterfaceToNew($toegang, 'weging'));
Route::get('mat/{toegang}', fn($toegang) => app(DeviceToegangController::class)->redirectInterfaceToNew($toegang, 'mat'));
Route::get('jury/{toegang}', fn($toegang) => app(DeviceToegangController::class)->redirectInterfaceToNew($toegang, 'jury'));
Route::get('spreker/{toegang}', fn($toegang) => app(DeviceToegangController::class)->redirectInterfaceToNew($toegang, 'spreker'));
Route::get('dojo/{toegang}', fn($toegang) => app(DeviceToegangController::class)->redirectInterfaceToNew($toegang, 'dojo'));

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
| Local Server Sync Routes (Redundancy System)
|--------------------------------------------------------------------------
*/
Route::prefix('local-server')->name('local.')->group(function () {
    // Setup
    Route::get('/setup', [LocalSyncController::class, 'setup'])->name('setup');
    Route::post('/setup', [LocalSyncController::class, 'saveSetup'])->name('setup.save');

    // Status & Health
    Route::get('/status', [LocalSyncController::class, 'status'])->name('status');
    Route::get('/health', [LocalSyncController::class, 'health'])->name('health');
    Route::get('/heartbeat', [LocalSyncController::class, 'heartbeat'])->name('heartbeat');

    // Sync
    Route::get('/sync', [LocalSyncController::class, 'syncData'])->name('sync');
    Route::get('/sync/{toernooi}', [LocalSyncController::class, 'syncToernooi'])->name('sync.toernooi');
    Route::post('/receive-sync', [LocalSyncController::class, 'receiveSync'])->name('receive-sync');
    Route::get('/standby-status', [LocalSyncController::class, 'standbyStatus'])->name('standby-status');

    // Standby sync UI
    Route::get('/standby-sync', [LocalSyncController::class, 'standbySyncUI'])->name('standby-sync');

    // Health dashboard (monitoring)
    Route::get('/health-dashboard', [LocalSyncController::class, 'healthDashboard'])->name('health-dashboard');

    // Pre-flight check wizard
    Route::get('/preflight', [LocalSyncController::class, 'preflight'])->name('preflight');

    // Startup wizard (step-by-step guide for tournament day)
    Route::get('/opstarten', [LocalSyncController::class, 'startupWizard'])->name('startup-wizard');

    // Auto-sync (downloads latest data from cloud)
    Route::get('/auto-sync', [LocalSyncController::class, 'autoSync'])->name('auto-sync');
    Route::post('/auto-sync', [LocalSyncController::class, 'executeAutoSync'])->name('auto-sync.execute');
    Route::get('/sync-status', [LocalSyncController::class, 'syncStatus'])->name('sync-status');

    // Emergency failover
    Route::get('/emergency', [LocalSyncController::class, 'emergencyFailover'])->name('emergency-failover');
    Route::post('/emergency', [LocalSyncController::class, 'executeEmergencyFailover'])->name('emergency-failover.execute');

    // Simple UI (for non-technical users)
    Route::get('/simple', [LocalSyncController::class, 'simple'])->name('simple');
    Route::get('/internet-status', [LocalSyncController::class, 'internetStatus'])->name('internet-status');
    Route::get('/queue-status', [LocalSyncController::class, 'queueStatus'])->name('queue-status');
    Route::post('/sync-now', [LocalSyncController::class, 'syncNow'])->name('sync-now');
    Route::post('/push-sync', [LocalSyncController::class, 'pushSync'])->name('push-sync');
    Route::get('/activate', [LocalSyncController::class, 'activate'])->name('activate');

    // Dashboard
    Route::get('/', [LocalSyncController::class, 'dashboard'])->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Public Pages: /{org}/{toernooi}/...
| No authentication required - vrij toegankelijk voor bezoekers
| IMPORTANT: These routes must be LAST to avoid conflicts with other routes
|--------------------------------------------------------------------------
*/
Route::prefix('{organisator}/{toernooi}')->name('publiek.')->group(function () {
    // Main public page (PWA)
    Route::get('/', [PubliekController::class, 'index'])->name('index');

    // API routes for public page (rate limited)
    Route::middleware('throttle:public-api')->group(function () {
        Route::get('zoeken', [PubliekController::class, 'zoeken'])->name('zoeken');
        Route::post('scan-qr', [PubliekController::class, 'scanQR'])->name('scan-qr');
        Route::post('weging/{judoka}/registreer', [PubliekController::class, 'registreerGewicht'])->name('weging.registreer');
        Route::post('favorieten', [PubliekController::class, 'favorieten'])->name('favorieten');
        Route::get('matten', [PubliekController::class, 'matten'])->name('matten');
    });
    Route::get('manifest.json', [PubliekController::class, 'manifest'])->name('manifest');
    Route::get('uitslagen.csv', [PubliekController::class, 'exportUitslagen'])->name('export-uitslagen');
})
->where('organisator', '^(?!admin|login|logout|registreren|weegkaart|coach-kaart|mollie|betaling|help|dashboard|local-server).*$')
->where('toernooi', '^(?!dashboard|clubs|templates|presets|toernooi).*$');

// Legacy public routes - redirect to new URL structure
Route::get('/publiek/{toernooiSlug}/zoeken', function($toernooiSlug) {
    $toernooi = \App\Models\Toernooi::where('slug', $toernooiSlug)->first();
    if (!$toernooi) abort(404);
    return redirect()->route('publiek.index', [
        'organisator' => $toernooi->organisator->slug,
        'toernooi' => $toernooi->slug
    ]);
})->name('publiek.zoeken.legacy');

Route::get('/{toernooiSlug}', function($toernooiSlug) {
    $toernooi = \App\Models\Toernooi::where('slug', $toernooiSlug)->first();
    if (!$toernooi) abort(404);
    return redirect()->route('publiek.index', [
        'organisator' => $toernooi->organisator->slug,
        'toernooi' => $toernooi->slug
    ]);
})
->name('publiek.index.legacy')
->where('toernooiSlug', '^(?!admin|login|logout|registreren|organisator|toernooi|coach|team|weging|mat|jury|spreker|dojo|weegkaart|coach-kaart|publiek|mollie|betaling|help|dashboard|local-server).*$');
