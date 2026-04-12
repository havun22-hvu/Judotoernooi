<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AutofixProposal;
use App\Models\Betaling;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        // Only sitebeheerder can access this page
        $user = auth('organisator')->user();
        if (!$user || !$user->isSitebeheerder()) {
            abort(403, 'Alleen sitebeheerders hebben toegang tot deze pagina.');
        }

        // Group toernooien by organisator for superadmin overview
        $organisatoren = Organisator::with(['toernooien' => function($q) {
            $q->withCount(['judokas', 'poules'])
              ->orderByDesc('datum');
        }])
        ->withCount(['clubs', 'toernooiTemplates'])
        ->orderBy('naam')
        ->get();

        // Also get toernooien without organisator (legacy/orphaned)
        $toernooienZonderOrganisator = Toernooi::whereDoesntHave('organisatoren')
            ->with('organisator')
            ->withCount(['judokas', 'poules'])
            ->orderByDesc('updated_at')
            ->get();

        // --- Widget data ---

        // 1. Omzet overzicht (toernooi upgrades)
        $omzetDezeMaand = ToernooiBetaling::where('status', 'paid')
            ->whereMonth('betaald_op', now()->month)
            ->whereYear('betaald_op', now()->year)
            ->sum('bedrag');
        $omzetVorigeMaand = ToernooiBetaling::where('status', 'paid')
            ->whereMonth('betaald_op', now()->subMonth()->month)
            ->whereYear('betaald_op', now()->subMonth()->year)
            ->sum('bedrag');
        $omzetTotaal = ToernooiBetaling::where('status', 'paid')->sum('bedrag');
        $openBetalingen = ToernooiBetaling::whereIn('status', ['open', 'pending'])->count();

        // Inschrijfgeld (platform fees)
        $inschrijfgeldDezeMaand = Betaling::where('status', 'paid')
            ->whereMonth('betaald_op', now()->month)
            ->whereYear('betaald_op', now()->year)
            ->sum('bedrag');
        $inschrijfgeldTotaal = Betaling::where('status', 'paid')->sum('bedrag');

        // Wimpel abo's
        $actieveAbos = Organisator::where('wimpel_abo_actief', true)->count();

        // 2. Vandaag & binnenkort
        $toernooienVandaag = Toernooi::whereDate('datum', today())
            ->with('organisator')->withCount('judokas')->get();
        $toernooienDezeWeek = Toernooi::whereBetween('datum', [today()->addDay(), today()->endOfWeek()])
            ->with('organisator')->withCount('judokas')->orderBy('datum')->get();
        $toernooienKomendeMaand = Toernooi::whereBetween('datum', [today()->endOfWeek()->addDay(), today()->addDays(30)])
            ->whereNull('afgesloten_at')
            ->with('organisator')->withCount('judokas')->orderBy('datum')->get();

        // 3. Klant gezondheid
        $klantenActief = Organisator::where('is_sitebeheerder', false)
            ->where('laatste_login', '>=', now()->subDays(7))->count();
        $klantenInactief = Organisator::where('is_sitebeheerder', false)
            ->whereBetween('laatste_login', [now()->subDays(30), now()->subDays(7)])->count();
        $klantenRisico = Organisator::where('is_sitebeheerder', false)
            ->where(fn($q) => $q->where('laatste_login', '<', now()->subDays(30))
                ->orWhereNull('laatste_login'))->count();
        $klantenNieuw = Organisator::where('is_sitebeheerder', false)
            ->where('created_at', '>=', now()->startOfMonth())->count();

        // 4. Recente activiteit
        $recenteActiviteit = ActivityLog::with('toernooi')
            ->latest()->take(10)->get();

        // 5. Systeem status
        $autofixVandaag = AutofixProposal::whereDate('created_at', today())->count();
        $autofixPending = AutofixProposal::where('status', 'pending')->count();
        $autofixApplied = AutofixProposal::where('status', 'approved')
            ->whereDate('applied_at', today())->count();
        $laatsteError = AutofixProposal::latest()->first()?->created_at;

        return view('pages.toernooi.index', compact(
            'organisatoren', 'toernooienZonderOrganisator',
            // Omzet
            'omzetDezeMaand', 'omzetVorigeMaand', 'omzetTotaal', 'openBetalingen',
            'inschrijfgeldDezeMaand', 'inschrijfgeldTotaal', 'actieveAbos',
            // Vandaag & binnenkort
            'toernooienVandaag', 'toernooienDezeWeek', 'toernooienKomendeMaand',
            // Klant gezondheid
            'klantenActief', 'klantenInactief', 'klantenRisico', 'klantenNieuw',
            // Recente activiteit
            'recenteActiviteit',
            // Systeem status
            'autofixVandaag', 'autofixPending', 'autofixApplied', 'laatsteError',
        ));
    }
}
