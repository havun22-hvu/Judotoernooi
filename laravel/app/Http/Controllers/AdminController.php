<?php

namespace App\Http\Controllers;

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
            'is_premium' => 'boolean',
            'is_test' => 'boolean',
        ]);

        // Handle boolean checkboxes
        $validated['is_premium'] = $request->has('is_premium');
        $validated['is_test'] = $request->has('is_test');

        $klant->update($validated);

        return redirect()
            ->route('admin.klanten')
            ->with('success', 'Klantgegevens bijgewerkt');
    }
}
