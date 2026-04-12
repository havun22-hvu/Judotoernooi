<?php

namespace App\Http\Controllers;

use App\Models\AutofixProposal;
use App\Models\Organisator;
use App\Models\ToernooiBetaling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Check if user is sitebeheerder
     */
    private function checkSitebeheerder(): void
    {
        $user = auth('organisator')->user();
        if (!$user || !$user->isSitebeheerder()) {
            abort(403, 'Alleen sitebeheerders hebben toegang tot deze pagina.');
        }
    }

    /**
     * List all klanten (organisatoren)
     */
    public function klanten(): View
    {
        $this->checkSitebeheerder();

        $klanten = Organisator::where('is_sitebeheerder', false)
            ->withCount(['toernooien', 'clubs', 'toernooiTemplates'])
            ->orderBy('naam')
            ->get();

        return view('pages.admin.klanten', compact('klanten'));
    }

    /**
     * Show edit form for a klant
     */
    public function editKlant(Organisator $klant): View
    {
        $this->checkSitebeheerder();

        // Load extra relations for the detail view
        $klant->loadCount(['toernooien', 'clubs', 'toernooiTemplates']);
        $klant->load(['toernooien' => function ($q) {
            $q->withCount(['judokas', 'poules'])->orderByDesc('datum');
        }]);

        // Load betalingen
        $betalingen = $klant->toernooiBetalingen()
            ->with('toernooi')
            ->orderByDesc('created_at')
            ->get();

        return view('pages.admin.klant-edit', compact('klant', 'betalingen'));
    }

    /**
     * Update klant details
     */
    public function updateKlant(Request $request, Organisator $klant): RedirectResponse
    {
        $this->checkSitebeheerder();

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:organisators,email,' . $klant->id,
            'telefoon' => 'nullable|string|max:50',
            'organisatie_naam' => 'nullable|string|max:255',
            'kvk_nummer' => 'nullable|string|max:50',
            'btw_nummer' => 'nullable|string|max:50',
            'straat' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'plaats' => 'nullable|string|max:255',
            'land' => 'nullable|string|max:100',
            'contactpersoon' => 'nullable|string|max:255',
            'factuur_email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'is_test' => 'boolean',
            'kortingsregeling' => 'boolean',
            'wimpel_abo_actief' => 'boolean',
            'wimpel_abo_start' => 'nullable|date',
            'wimpel_abo_einde' => 'nullable|date|after_or_equal:wimpel_abo_start',
            'wimpel_abo_prijs' => 'nullable|numeric|min:0',
            'wimpel_abo_notities' => 'nullable|string|max:1000',
        ]);

        // Handle boolean checkboxes
        $validated['is_test'] = $request->has('is_test');
        $validated['kortingsregeling'] = $request->has('kortingsregeling');
        $validated['wimpel_abo_actief'] = $request->has('wimpel_abo_actief');

        // Auto-fill start/end dates when activating wimpel abo
        if ($validated['wimpel_abo_actief'] && !$klant->wimpel_abo_actief) {
            $validated['wimpel_abo_start'] = $validated['wimpel_abo_start'] ?? now()->toDateString();
            $validated['wimpel_abo_einde'] = $validated['wimpel_abo_einde'] ?? now()->addYear()->toDateString();
        }

        $klant->update($validated);

        return redirect()
            ->route('admin.klanten')
            ->with('success', 'Klantgegevens bijgewerkt');
    }

    /**
     * All invoices/payments overview
     */
    public function facturen(): View
    {
        $this->checkSitebeheerder();

        $betalingen = ToernooiBetaling::with(['organisator', 'toernooi'])
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'totaal_betaald' => $betalingen->where('status', 'paid')->sum('bedrag'),
            'aantal_betaald' => $betalingen->where('status', 'paid')->count(),
            'aantal_open' => $betalingen->where('status', 'open')->count(),
        ];

        return view('pages.admin.facturen', compact('betalingen', 'stats'));
    }

    /**
     * AutoFix proposals overview
     */
    public function autofix(): View
    {
        $this->checkSitebeheerder();

        $proposals = AutofixProposal::latest()->take(50)->get();

        $stats = [
            'total' => AutofixProposal::count(),
            'applied' => AutofixProposal::where('status', 'applied')->count(),
            'failed' => AutofixProposal::where('status', 'failed')->count(),
            'pending' => AutofixProposal::where('status', 'pending')->count(),
            'errors' => AutofixProposal::where('status', 'error')->count(),
        ];

        // Aggregate metrics: success rates
        $metrics = $this->getAutofixMetrics();

        return view('pages.admin.autofix', compact('proposals', 'stats', 'metrics'));
    }

    /**
     * Calculate aggregate AutoFix metrics for the dashboard.
     */
    private function getAutofixMetrics(): array
    {
        // Success rate last 7 days
        $last7Total = AutofixProposal::where('created_at', '>=', now()->subDays(7))
            ->whereIn('status', ['applied', 'failed', 'error'])
            ->count();
        $last7Applied = AutofixProposal::where('created_at', '>=', now()->subDays(7))
            ->where('status', 'applied')
            ->count();

        // Success rate last 30 days
        $last30Total = AutofixProposal::where('created_at', '>=', now()->subDays(30))
            ->whereIn('status', ['applied', 'failed', 'error'])
            ->count();
        $last30Applied = AutofixProposal::where('created_at', '>=', now()->subDays(30))
            ->where('status', 'applied')
            ->count();

        // Average time-to-fix (created_at → applied_at) in minutes
        $isSqlite = DB::getDriverName() === 'sqlite';
        $avgTimeToFixQuery = $isSqlite
            ? "AVG((julianday(applied_at) - julianday(created_at)) * 1440) as avg_minutes"
            : 'AVG(TIMESTAMPDIFF(MINUTE, created_at, applied_at)) as avg_minutes';

        $avgTimeToFix = AutofixProposal::where('status', 'applied')
            ->whereNotNull('applied_at')
            ->selectRaw($avgTimeToFixQuery)
            ->value('avg_minutes');

        // Most common error types (top 5)
        $commonErrors = AutofixProposal::select('exception_class', DB::raw('COUNT(*) as count'))
            ->groupBy('exception_class')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'class' => class_basename($row->exception_class),
                'full_class' => $row->exception_class,
                'count' => $row->count,
            ]);

        // Error trend: compare last 7 days vs previous 7 days
        $currentWeek = AutofixProposal::where('created_at', '>=', now()->subDays(7))->count();
        $previousWeek = AutofixProposal::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
        $trend = $previousWeek > 0
            ? round((($currentWeek - $previousWeek) / $previousWeek) * 100)
            : ($currentWeek > 0 ? 100 : 0);

        return [
            'success_rate_7d' => $last7Total > 0 ? round(($last7Applied / $last7Total) * 100) : null,
            'success_rate_30d' => $last30Total > 0 ? round(($last30Applied / $last30Total) * 100) : null,
            'avg_time_to_fix_minutes' => $avgTimeToFix !== null ? round((float) $avgTimeToFix) : null,
            'common_errors' => $commonErrors,
            'trend_percentage' => $trend,
            'trend_direction' => $currentWeek > $previousWeek ? 'up' : ($currentWeek < $previousWeek ? 'down' : 'stable'),
            'current_week_count' => $currentWeek,
            'previous_week_count' => $previousWeek,
        ];
    }

    /**
     * Impersonate: log in as another organisator (sitebeheerder only)
     */
    public function impersonate(Organisator $klant): RedirectResponse
    {
        $this->checkSitebeheerder();

        $admin = auth('organisator')->user();

        if ($admin->id === $klant->id) {
            return redirect()->route('admin.klanten')
                ->with('error', 'Je kunt niet als jezelf impersoneren');
        }

        session()->put('impersonating_from', $admin->id);
        Auth::guard('organisator')->login($klant);

        return redirect()->route('organisator.dashboard', ['organisator' => $klant->slug]);
    }

    /**
     * Stop impersonating and return to admin account
     */
    public function impersonateStop(): RedirectResponse
    {
        $adminId = session('impersonating_from');

        if (!$adminId) {
            return redirect()->route('admin.klanten');
        }

        $admin = Organisator::find($adminId);

        if (!$admin || !$admin->isSitebeheerder()) {
            session()->forget('impersonating_from');
            return redirect()->route('login');
        }

        session()->forget('impersonating_from');
        Auth::guard('organisator')->login($admin);

        return redirect()->route('admin.klanten');
    }

    /**
     * Delete a klant (organisator) and all related data
     */
    public function destroyKlant(Organisator $klant): RedirectResponse
    {
        $this->checkSitebeheerder();

        if ($klant->isSitebeheerder()) {
            return redirect()->route('admin.klanten')
                ->with('error', 'Sitebeheerder kan niet verwijderd worden');
        }

        $naam = $klant->naam;

        try {
            \DB::transaction(function () use ($klant) {
                foreach ($klant->toernooien as $toernooi) {
                    $pouleIds = $toernooi->poules()->pluck('id');
                    \App\Models\Wedstrijd::whereIn('poule_id', $pouleIds)->delete();
                    \DB::table('poule_judoka')->whereIn('poule_id', $pouleIds)->delete();
                    $toernooi->poules()->delete();
                    $toernooi->judokas()->delete();
                    $toernooi->blokken()->delete();
                    $toernooi->matten()->delete();
                    \DB::table('club_toernooi')->where('toernooi_id', $toernooi->id)->delete();
                    \DB::table('club_uitnodigingen')->where('toernooi_id', $toernooi->id)->delete();
                    \DB::table('betalingen')->where('toernooi_id', $toernooi->id)->delete();
                    \DB::table('toernooi_betalingen')->where('toernooi_id', $toernooi->id)->delete();
                    \DB::table('device_toegangen')->where('toernooi_id', $toernooi->id)->delete();
                    \DB::table('coach_kaarten')->where('toernooi_id', $toernooi->id)->delete();
                    \DB::table('coaches')->where('toernooi_id', $toernooi->id)->delete();
                    $toernooi->delete();
                }

                $klant->clubs()->delete();
                \DB::table('toernooi_templates')->where('organisator_id', $klant->id)->delete();
                \DB::table('gewichtsklassen_presets')->where('organisator_id', $klant->id)->delete();

                $klant->delete();
            });
        } catch (\Exception $e) {
            \Log::error("Klant delete failed: {$e->getMessage()}", ['klant_id' => $klant->id]);

            return redirect()->route('admin.klanten')
                ->with('error', "Kon '{$naam}' niet verwijderen: {$e->getMessage()}");
        }

        return redirect()->route('admin.klanten')
            ->with('success', "Klant '{$naam}' en alle bijbehorende data verwijderd");
    }
}
