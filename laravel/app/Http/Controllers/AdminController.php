<?php

namespace App\Http\Controllers;

use App\Models\AutofixProposal;
use App\Models\Organisator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ];

        return view('pages.admin.autofix', compact('proposals', 'stats'));
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
