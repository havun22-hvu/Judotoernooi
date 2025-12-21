<?php

namespace App\Http\Controllers;

use App\Http\Requests\ToernooiRequest;
use App\Models\Toernooi;
use App\Services\PouleIndelingService;
use App\Services\ToernooiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToernooiController extends Controller
{
    public function __construct(
        private ToernooiService $toernooiService,
        private PouleIndelingService $pouleIndelingService
    ) {}

    public function index(): View
    {
        $toernooien = Toernooi::orderByDesc('datum')->paginate(10);

        return view('pages.toernooi.index', compact('toernooien'));
    }

    public function create(): View
    {
        return view('pages.toernooi.create');
    }

    public function store(ToernooiRequest $request): RedirectResponse
    {
        $toernooi = $this->toernooiService->initialiseerToernooi($request->validated());

        return redirect()
            ->route('toernooi.show', $toernooi)
            ->with('success', 'Toernooi succesvol aangemaakt');
    }

    public function show(Toernooi $toernooi): View
    {
        $statistieken = $this->toernooiService->getStatistieken($toernooi);

        return view('pages.toernooi.show', compact('toernooi', 'statistieken'));
    }

    public function edit(Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()->orderBy('nummer')->get();
        return view('pages.toernooi.edit', compact('toernooi', 'blokken'));
    }

    public function update(ToernooiRequest $request, Toernooi $toernooi): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        // Process gewichtsklassen from JSON input (includes leeftijdsgrenzen)
        if ($request->has('gewichtsklassen_json') && $request->input('gewichtsklassen_json')) {
            $data['gewichtsklassen'] = json_decode($request->input('gewichtsklassen_json'), true) ?? [];
        } elseif (isset($data['gewichtsklassen'])) {
            // Fallback: process from individual form fields
            $gewichtsklassen = [];
            $standaard = Toernooi::getStandaardGewichtsklassen();
            $leeftijden = $request->input('gewichtsklassen_leeftijd', []);
            $labels = $request->input('gewichtsklassen_label', []);

            foreach ($data['gewichtsklassen'] as $key => $value) {
                $gewichten = array_map('trim', explode(',', $value));
                $gewichten = array_filter($gewichten, fn($g) => !empty($g));
                $gewichtsklassen[$key] = [
                    'label' => $labels[$key] ?? $standaard[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key)),
                    'max_leeftijd' => (int) ($leeftijden[$key] ?? $standaard[$key]['max_leeftijd'] ?? 99),
                    'gewichten' => array_values($gewichten),
                ];
            }

            $data['gewichtsklassen'] = $gewichtsklassen;
        }

        // Remove temporary fields from data
        unset($data['gewichtsklassen_leeftijd'], $data['gewichtsklassen_label']);

        // Check if sorting settings changed
        $volgordeGewijzigd = isset($data['judoka_code_volgorde'])
            && $data['judoka_code_volgorde'] !== $toernooi->judoka_code_volgorde;

        // Handle gebruik_gewichtsklassen checkbox (0 from hidden field, 1 from checkbox)
        $nieuweGebruikGewichtsklassen = (bool) ($data['gebruik_gewichtsklassen'] ?? 1);
        $oudeGebruikGewichtsklassen = $toernooi->gebruik_gewichtsklassen === null ? true : $toernooi->gebruik_gewichtsklassen;
        $gewichtsklassenGewijzigd = $nieuweGebruikGewichtsklassen !== $oudeGebruikGewichtsklassen;
        $data['gebruik_gewichtsklassen'] = $nieuweGebruikGewichtsklassen;

        $toernooi->update($data);

        // Recalculate judoka codes if sorting settings changed
        if (($volgordeGewijzigd || $gewichtsklassenGewijzigd) && $toernooi->judokas()->exists()) {
            $aantal = $this->pouleIndelingService->herberekenJudokaCodes($toernooi);
            $extraMessage = " ({$aantal} judoka codes bijgewerkt)";
        } else {
            $extraMessage = '';
        }

        // Return JSON for AJAX requests (auto-save)
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'codes_updated' => $volgordeGewijzigd ? ($aantal ?? 0) : 0,
            ]);
        }

        return redirect()
            ->route('toernooi.edit', $toernooi)
            ->with('success', 'Toernooi bijgewerkt' . $extraMessage);
    }

    public function destroy(Toernooi $toernooi): RedirectResponse
    {
        // Only sitebeheerder (admin) can delete
        $organisator = auth('organisator')->user();
        if (!$organisator || !$organisator->isSitebeheerder()) {
            return redirect()
                ->route('toernooi.index')
                ->with('error', 'Alleen een sitebeheerder kan toernooien verwijderen');
        }

        $naam = $toernooi->naam;

        // Delete all related data explicitly
        $pouleIds = $toernooi->poules()->pluck('id');
        \App\Models\Wedstrijd::whereIn('poule_id', $pouleIds)->delete();
        \DB::table('poule_judoka')->whereIn('poule_id', $pouleIds)->delete();
        $toernooi->poules()->delete();
        $toernooi->judokas()->delete();
        $toernooi->blokken()->delete();
        $toernooi->matten()->delete();
        $toernooi->delete();

        return redirect()
            ->route('toernooi.index')
            ->with('success', "Toernooi '{$naam}' volledig verwijderd");
    }

    /**
     * Reset tournament - keeps settings and judokas, clears poules/wedstrijden
     */
    public function reset(Toernooi $toernooi): RedirectResponse
    {
        // Delete wedstrijden and poules
        $pouleIds = $toernooi->poules()->pluck('id');
        $wedstrijdCount = \App\Models\Wedstrijd::whereIn('poule_id', $pouleIds)->delete();
        $pivotCount = \DB::table('poule_judoka')->whereIn('poule_id', $pouleIds)->delete();
        $pouleCount = $toernooi->poules()->delete();

        // Reset judoka status
        $toernooi->judokas()->update([
            'gewicht_gewogen' => null,
            'aanwezigheid' => 'onbekend',
            'aantal_wegingen' => 0,
        ]);

        // Reset blokken
        $toernooi->blokken()->update([
            'weging_gesloten' => false,
        ]);

        // Reset SQLite sequences if applicable
        if (\DB::getDriverName() === 'sqlite') {
            $minPouleId = \App\Models\Poule::min('id') ?? 0;
            $minWedstrijdId = \App\Models\Wedstrijd::min('id') ?? 0;
            if ($minPouleId > 0) {
                \DB::statement("UPDATE sqlite_sequence SET seq = ? WHERE name = 'poules'", [$minPouleId - 1]);
            } else {
                \DB::statement("DELETE FROM sqlite_sequence WHERE name = 'poules'");
            }
            if ($minWedstrijdId > 0) {
                \DB::statement("UPDATE sqlite_sequence SET seq = ? WHERE name = 'wedstrijden'", [$minWedstrijdId - 1]);
            } else {
                \DB::statement("DELETE FROM sqlite_sequence WHERE name = 'wedstrijden'");
            }
        }

        return redirect()
            ->route('toernooi.show', $toernooi)
            ->with('success', "Toernooi gereset: {$pouleCount} poules, {$wedstrijdCount} wedstrijden verwijderd. Judoka's behouden.");
    }

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
     * Dashboard for authenticated organisators
     */
    public function organisatorDashboard(): View
    {
        $organisator = auth('organisator')->user();

        if ($organisator->isSitebeheerder()) {
            $toernooien = Toernooi::orderBy('datum', 'desc')->get();
        } else {
            $toernooien = $organisator->toernooien()->orderBy('datum', 'desc')->get();
        }

        return view('organisator.dashboard', compact('organisator', 'toernooien'));
    }

    public function updateWachtwoorden(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $rollen = ['admin', 'jury', 'weging', 'mat', 'spreker'];
        $updated = [];

        foreach ($rollen as $rol) {
            $wachtwoord = $request->input("wachtwoord_{$rol}");
            if ($wachtwoord && strlen($wachtwoord) > 0) {
                $toernooi->setWachtwoord($rol, $wachtwoord);
                $updated[] = ucfirst($rol);
            }
        }

        if (empty($updated)) {
            return redirect()
                ->route('toernooi.edit', $toernooi)
                ->with('info', 'Geen wachtwoorden gewijzigd');
        }

        return redirect()
            ->route('toernooi.edit', $toernooi)
            ->with('success', 'Wachtwoorden bijgewerkt voor: ' . implode(', ', $updated));
    }

    public function updateBloktijden(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $bloktijden = $request->input('blokken', []);

        foreach ($bloktijden as $blokId => $tijden) {
            $blok = $toernooi->blokken()->find($blokId);
            if ($blok) {
                $blok->update([
                    'weging_start' => $tijden['weging_start'] ?: null,
                    'weging_einde' => $tijden['weging_einde'] ?: null,
                    'starttijd' => $tijden['starttijd'] ?: null,
                ]);
            }
        }

        return redirect()
            ->route('toernooi.edit', $toernooi)
            ->with('success', 'Bloktijden bijgewerkt');
    }
}
