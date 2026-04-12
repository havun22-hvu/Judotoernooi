<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Services\ToernooiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganisatorDashboardController extends Controller
{
    public function __construct(
        private ToernooiService $toernooiService,
    ) {}

    /**
     * Legacy dashboard (redirects to new URL structure)
     */
    public function dashboard(): View
    {
        $toernooi = $this->toernooiService->getActiefToernooi();

        if (!$toernooi) {
            return view('pages.toernooi.geen-actief');
        }

        $statistieken = $this->toernooiService->getStatistieken($toernooi);

        return view('pages.toernooi.dashboard', compact('toernooi', 'statistieken'));
    }

    /**
     * Redirect to new URL structure for organisator dashboard
     */
    public function redirectToOrganisatorDashboard(): RedirectResponse
    {
        $organisator = auth('organisator')->user();
        return redirect()->route('organisator.dashboard', ['organisator' => $organisator->slug]);
    }

    /**
     * Dashboard for authenticated organisators (new URL: /{organisator-slug}/dashboard)
     */
    public function organisatorDashboard(Organisator $organisator): View
    {
        $loggedIn = auth('organisator')->user();

        // Verify access: either viewing own dashboard or is sitebeheerder
        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403, 'Je hebt geen toegang tot dit dashboard.');
        }

        // Fresh load to ensure we have latest toernooien (not cached from login)
        $organisator = $organisator->fresh();

        // Everyone sees only their own toernooien — sitebeheerder uses /admin for other organisatoren
        $alleToernooien = $organisator->toernooien()->with('organisator')->orderBy('datum', 'desc')->get();
        $toernooien = $alleToernooien->where('is_gearchiveerd', false);
        $gearchiveerd = $alleToernooien->where('is_gearchiveerd', true);

        // Restore organisator's locale when returning to dashboard
        $locale = $organisator->locale ?? config('app.locale');
        session()->put('locale', $locale);
        app()->setLocale($locale);

        return view('organisator.dashboard', compact('organisator', 'toernooien', 'gearchiveerd'));
    }
}
