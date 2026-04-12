<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles judoka-to-poule operations: matching, drag-and-drop moves, and unregistration.
 */
class PouleJudokaController extends Controller
{
    public function __construct(
        private WedstrijdSchemaService $wedstrijdService
    ) {}

    /**
     * Zoek mogelijke matches voor een judoka (orphan handling)
     * Toont alleen relevante poules en ook poules uit volgende (oudere) categorie
     *
     * Query params:
     * - wedstrijddag=1: Filter op blok beschikbaarheid (weging status)
     * - from_poule_id: Huidige poule ID (voor wedstrijddag mode)
     */
    public function zoekMatch(Organisator $organisator, Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $huidigJaar = now()->year;
        $isWedstrijddag = $request->boolean('wedstrijddag', false);
        $fromPouleId = $request->input('from_poule_id');

        // Vind huidige poule van judoka
        $huidigePoule = $fromPouleId
            ? Poule::find($fromPouleId)
            : $judoka->poules()->where('toernooi_id', $toernooi->id)->first();

        // Bepaal huidige blok voor wedstrijddag filtering
        $huidigeBlok = $huidigePoule?->blok;

        // Haal config parameters op
        $config = $toernooi->getAlleGewichtsklassen();
        $categorieKey = $huidigePoule?->categorie_key;
        $categorieConfig = $categorieKey ? ($config[$categorieKey] ?? []) : [];
        $maxKgVerschil = (float) ($categorieConfig['max_kg_verschil'] ?? $toernooi->max_kg_verschil ?? 3.0);
        $maxLftVerschil = (int) ($categorieConfig['max_leeftijd_verschil'] ?? $toernooi->max_leeftijd_verschil ?? 2);

        // Judoka gegevens
        $judokaGewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
        $judokaLeeftijd = $judoka->geboortejaar ? $huidigJaar - $judoka->geboortejaar : 0;
        $judokaGeslacht = $judoka->geslacht; // M of V

        // Bepaal huidige leeftijdsklasse
        $huidigeLeeftijdsklasse = $huidigePoule?->leeftijdsklasse;

        // Haal ALLE poules op - we filteren later op basis van leeftijd/gewicht relevantie
        $poulesQuery = $toernooi->poules()->with(['judokas', 'blok']);
        if ($huidigePoule) {
            $poulesQuery->where('id', '!=', $huidigePoule->id);
        }
        $poules = $poulesQuery->get();

        // Laad alle blokken voor wedstrijddag filtering
        $blokken = $isWedstrijddag ? $toernooi->blokken()->orderBy('nummer')->get()->keyBy('id') : collect();

        $matches = [];

        foreach ($poules as $poule) {
            // Filter afwezige judoka's - alleen aanwezigen tellen mee voor ranges
            $judokasInPoule = $poule->judokas->filter(fn($j) => $j->aanwezigheid !== 'afwezig');

            if ($judokasInPoule->isEmpty()) {
                continue;
            }

            // Wedstrijddag: check blok beschikbaarheid
            $blokStatus = null;
            if ($isWedstrijddag && $poule->blok_id) {
                $pouleBlok = $blokken->get($poule->blok_id);

                if ($pouleBlok && $huidigeBlok) {
                    if ($pouleBlok->nummer < $huidigeBlok->nummer) {
                        // Eerder blok met gesloten weging
                        $blokStatus = 'earlier_closed';
                    } elseif ($pouleBlok->nummer === $huidigeBlok->nummer) {
                        $blokStatus = 'same';
                    } else {
                        // Later blok - zou niet moeten voorkomen want weging open
                        $blokStatus = 'later';
                    }
                }
            }

            // Check geslacht: M/V moet matchen
            // Bepaal het geslacht van de poule op basis van de judoka's erin
            $geslachtenInPoule = $judokasInPoule->pluck('geslacht')->unique()->filter();
            $isGemengdePoule = $geslachtenInPoule->count() > 1;
            $pouleGeslacht = $geslachtenInPoule->first();

            // Skip als geslacht niet matcht (tenzij gemengde poule)
            if (!$isGemengdePoule && $pouleGeslacht && $judokaGeslacht && $pouleGeslacht !== $judokaGeslacht) {
                continue;
            }

            // Huidige statistieken
            $huidigeGewichten = $judokasInPoule->map(fn($j) => $j->gewicht_gewogen ?? $j->gewicht ?? 0)->filter();
            $huidigeLeeftijden = $judokasInPoule->map(fn($j) => $j->geboortejaar ? $huidigJaar - $j->geboortejaar : 0)->filter();

            $huidigeMinKg = $huidigeGewichten->min();
            $huidigeMaxKg = $huidigeGewichten->max();
            $huidigeMinLft = $huidigeLeeftijden->min();
            $huidigeMaxLft = $huidigeLeeftijden->max();

            // Nieuwe statistieken na toevoegen judoka
            $nieuweMinKg = min($huidigeMinKg, $judokaGewicht);
            $nieuweMaxKg = max($huidigeMaxKg, $judokaGewicht);
            $nieuweMinLft = min($huidigeMinLft, $judokaLeeftijd);
            $nieuweMaxLft = max($huidigeMaxLft, $judokaLeeftijd);

            $nieuweKgRange = $nieuweMaxKg - $nieuweMinKg;
            $nieuweLftRange = $nieuweMaxLft - $nieuweMinLft;

            // Bereken overschrijding
            $kgOverschrijding = max(0, $nieuweKgRange - $maxKgVerschil);
            $lftOverschrijding = max(0, $nieuweLftRange - $maxLftVerschil);

            // Is dit een andere categorie?
            $isCategorieOverschrijding = $huidigeLeeftijdsklasse && $poule->leeftijdsklasse !== $huidigeLeeftijdsklasse;

            // ============================================================
            // BEPAAL STATUS - TWEE VERSCHILLENDE SYSTEMEN
            // ============================================================
            $isVasteKlassen = $maxKgVerschil == 0;

            if ($isVasteKlassen) {
                // --------------------------------------------------------
                // VASTE GEWICHTSKLASSEN (bijv. Jeugd -27 kg)
                // Judoka past in EXACT 1 categorie: leeftijdsklasse + gewichtsklasse
                // --------------------------------------------------------
                $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

                // Stap 1: Bepaal de juiste gewichtsklasse voor dit gewicht
                $beschikbareKlassen = Judoka::where('leeftijdsklasse', $judoka->leeftijdsklasse)
                    ->where('toernooi_id', $toernooi->id)
                    ->whereNotNull('gewichtsklasse')
                    ->distinct()
                    ->pluck('gewichtsklasse')
                    ->toArray();

                usort($beschikbareKlassen, fn($a, $b) =>
                    floatval(preg_replace('/[^0-9.]/', '', explode('-', $a)[0])) - floatval(preg_replace('/[^0-9.]/', '', explode('-', $b)[0]))
                );

                $juisteGewichtsklasse = null;
                foreach ($beschikbareKlassen as $klasse) {
                    $parts = explode('-', $klasse);
                    $limiet = floatval(preg_replace('/[^0-9.]/', '', end($parts)));
                    if (str_starts_with($klasse, '+') || $judokaGewicht <= $limiet + $tolerantie) {
                        $juisteGewichtsklasse = $klasse;
                        break;
                    }
                }

                // Stap 2: Check of poule exact matcht
                $leeftijdsklasseKlopt = $poule->leeftijdsklasse === $judoka->leeftijdsklasse;
                $gewichtsklasseKlopt = $poule->gewichtsklasse === $juisteGewichtsklasse;

                if ($leeftijdsklasseKlopt && $gewichtsklasseKlopt) {
                    $status = 'ok';
                    $kgOverschrijding = 0;
                } else {
                    $status = 'error';
                }

            } else {
                // --------------------------------------------------------
                // VARIABELE GEWICHTEN (max_kg_verschil > 0)
                // Check of gewicht/leeftijd spreiding binnen limieten blijft
                // --------------------------------------------------------
                $status = 'ok';
                if ($kgOverschrijding > 0 || $lftOverschrijding > 0) {
                    $status = ($kgOverschrijding <= 2 && $lftOverschrijding <= 1) ? 'warning' : 'error';
                }
            }

            $match = [
                'poule_id' => $poule->id,
                'poule_nummer' => $poule->nummer,
                'poule_titel' => $poule->getDisplayTitel() ?: "Poule #{$poule->nummer}",
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'categorie_overschrijding' => $isCategorieOverschrijding,
                'huidige_judokas' => $judokasInPoule->count(),
                'huidige_leeftijd' => $huidigeMinLft == $huidigeMaxLft ? "{$huidigeMinLft}j" : "{$huidigeMinLft}-{$huidigeMaxLft}j",
                'huidige_gewicht' => round($huidigeMinKg, 1) == round($huidigeMaxKg, 1)
                    ? round($huidigeMinKg, 1) . "kg"
                    : round($huidigeMinKg, 1) . "-" . round($huidigeMaxKg, 1) . "kg",
                'nieuwe_judokas' => $judokasInPoule->count() + 1,
                'nieuwe_leeftijd' => $nieuweMinLft == $nieuweMaxLft ? "{$nieuweMinLft}j" : "{$nieuweMinLft}-{$nieuweMaxLft}j",
                'nieuwe_gewicht' => round($nieuweMinKg, 1) == round($nieuweMaxKg, 1)
                    ? round($nieuweMinKg, 1) . "kg"
                    : round($nieuweMinKg, 1) . "-" . round($nieuweMaxKg, 1) . "kg",
                'kg_overschrijding' => round($kgOverschrijding, 1),
                'lft_overschrijding' => $lftOverschrijding,
                'status' => $status,
            ];

            // Voeg blok info toe voor wedstrijddag
            if ($isWedstrijddag && $poule->blok) {
                $match['blok_nummer'] = $poule->blok->nummer;
                $match['blok_naam'] = $poule->blok->naam;
                $match['blok_status'] = $blokStatus; // same, later, earlier_open
            }

            $matches[] = $match;
        }

        // Split in eigen categorie en andere categorie
        $eigenCategorie = array_filter($matches, fn($m) => !$m['categorie_overschrijding']);
        $andereCategorie = array_filter($matches, fn($m) => $m['categorie_overschrijding']);

        // Sorteer op status en kg overschrijding
        // Voor wedstrijddag: ook op blok (same > later > earlier_open)
        $sortFn = function ($a, $b) use ($isWedstrijddag) {
            // Wedstrijddag: sorteer eerst op blok status
            if ($isWedstrijddag) {
                $blokOrder = ['same' => 0, 'later' => 1, 'earlier_open' => 2];
                $blokCmp = ($blokOrder[$a['blok_status'] ?? ''] ?? 3) <=> ($blokOrder[$b['blok_status'] ?? ''] ?? 3);
                if ($blokCmp !== 0) return $blokCmp;
            }

            $statusOrder = ['ok' => 0, 'warning' => 1, 'error' => 2];
            $statusCmp = ($statusOrder[$a['status']] ?? 3) <=> ($statusOrder[$b['status']] ?? 3);
            if ($statusCmp !== 0) return $statusCmp;
            return $a['kg_overschrijding'] <=> $b['kg_overschrijding'];
        };

        usort($eigenCategorie, $sortFn);
        usort($andereCategorie, $sortFn);

        // Neem max 7 uit eigen categorie + max 5 uit andere categorie
        $eigenCategorie = array_slice($eigenCategorie, 0, 7);
        $andereCategorie = array_slice($andereCategorie, 0, 5);

        // Combineer: eigen categorie eerst, dan andere
        $matches = array_merge($eigenCategorie, $andereCategorie);

        $response = [
            'success' => true,
            'judoka' => [
                'id' => $judoka->id,
                'naam' => $judoka->naam,
                'gewicht' => $judokaGewicht,
                'leeftijd' => $judokaLeeftijd,
            ],
            'huidige_poule_id' => $huidigePoule?->id,
            'huidige_leeftijdsklasse' => $huidigeLeeftijdsklasse,
            'gebruik_gewichtsklassen' => $toernooi->gebruik_gewichtsklassen ?? false,
            'max_kg_verschil' => $maxKgVerschil,
            'max_lft_verschil' => $maxLftVerschil,
            'matches' => $matches,
        ];

        // Voeg wedstrijddag info toe
        if ($isWedstrijddag && $huidigeBlok) {
            $response['wedstrijddag'] = true;
            $response['huidige_blok'] = [
                'nummer' => $huidigeBlok->nummer,
                'naam' => $huidigeBlok->naam,
            ];
        }

        return response()->json($response);
    }

    /**
     * API endpoint for drag-and-drop judoka move
     */
    public function verplaatsJudokaApi(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'van_poule_id' => 'required|exists:poules,id',
            'naar_poule_id' => 'required|exists:poules,id',
        ]);

        $judoka = Judoka::findOrFail($validated['judoka_id']);
        $vanPoule = Poule::findOrFail($validated['van_poule_id']);
        $naarPoule = Poule::findOrFail($validated['naar_poule_id']);

        // Skip if same poule
        if ($vanPoule->id === $naarPoule->id) {
            return response()->json(['success' => true, 'message' => 'Geen wijziging']);
        }

        // Remove from current poule
        $vanPoule->judokas()->detach($judoka->id);

        // Add to new poule
        $nieuwePositie = $naarPoule->judokas()->count() + 1;
        $naarPoule->judokas()->attach($judoka->id, ['positie' => $nieuwePositie]);

        // Regenerate matches for both poules (need fresh judoka lists)
        $vanPoule->load('judokas');
        $naarPoule->load('judokas');
        $vanPoule->wedstrijden()->delete();
        $naarPoule->wedstrijden()->delete();
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($vanPoule);
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($naarPoule);

        // Update statistics and refresh to get final state
        $vanPoule->updateStatistieken();
        $naarPoule->updateStatistieken();
        $vanPoule->refresh();
        $naarPoule->refresh();

        // Update dynamic titles in DB (ranges for variable categories)
        $huidigJaar = now()->year;
        $this->updateDynamischeTitel($vanPoule, $this->berekenPouleRanges($vanPoule, $huidigJaar));
        $this->updateDynamischeTitel($naarPoule, $this->berekenPouleRanges($naarPoule, $huidigJaar));

        ActivityLogger::log($toernooi, 'verplaats_judoka', "{$judoka->naam} verplaatst van poule #{$vanPoule->nummer} naar poule #{$naarPoule->nummer}", [
            'model' => $judoka,
            'properties' => ['van_poule_id' => $vanPoule->id, 'naar_poule_id' => $naarPoule->id, 'van_nummer' => $vanPoule->nummer, 'naar_nummer' => $naarPoule->nummer],
        ]);

        // Check if judoka fits in new poule
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
        $judokaPastInPoule = $naarPoule->isDynamisch()
            ? ($naarPoule->isProblematischNaWeging() === null)
            : $judoka->isGewichtBinnenKlasse(null, $tolerantie, $naarPoule->gewichtsklasse);

        return response()->json([
            'success' => true,
            'message' => "{$judoka->naam} verplaatst naar {$naarPoule->getDisplayTitel()}",
            'judoka_id' => $judoka->id,
            'judoka_past_in_poule' => $judokaPastInPoule,
            'van_poule' => $this->buildPouleResponse($vanPoule),
            'naar_poule' => $this->buildPouleResponse($naarPoule),
        ]);
    }

    /**
     * Uitschrijven: judoka afmelden en uit poule halen (geen tegenstanders)
     */
    public function uitschrijvenJudoka(Organisator $organisator, Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        if ($judoka->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'message' => 'Judoka hoort niet bij dit toernooi'], 403);
        }

        $judoka->update(['aanwezigheid' => 'afgemeld']);

        // Remove from all poules
        $updatedPoules = [];
        foreach ($judoka->poules as $poule) {
            $poule->judokas()->detach($judoka->id);
            $poule->wedstrijden()->delete();
            $poule->load('judokas');
            $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
            $poule->updateStatistieken();
            $poule->refresh();
            $updatedPoules[] = $this->buildPouleResponse($poule);
        }

        ActivityLogger::log($toernooi, 'uitschrijven', "{$judoka->naam} uitgeschreven (geen tegenstanders)", [
            'model' => $judoka,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$judoka->naam} is uitgeschreven",
            'updated_poules' => $updatedPoules,
        ]);
    }

    /**
     * Build standard poule response data for drag-and-drop endpoints
     */
    /**
     * DO NOT REMOVE: Build standardized poule response for ALL mutation API endpoints.
     *
     * CRITICAL: The 'problemen' key MUST always be included. It contains the result of
     * Poule::checkPouleRegels() which checks weight AND age limits. The frontend JS
     * (updatePouleStats + updateProblematischePoules) depends on this to show/hide warnings.
     *
     * Every controller method that mutates a poule MUST return this response for affected poules.
     * If you remove 'problemen' or stop calling checkPouleRegels(), the poule warnings will
     * silently stop updating after drag/remove operations.
     *
     * @see Poule::checkPouleRegels() — the actual rule checking
     * @see resources/views/pages/poule/index.blade.php — updatePouleStats() JS function
     */
    private function buildPouleResponse(Poule $poule): array
    {
        $isDynamisch = $poule->isDynamisch();
        // DO NOT REMOVE: problemen must always be recalculated and included
        $problemen = $poule->checkPouleRegels();

        return [
            'id' => $poule->id,
            'nummer' => $poule->nummer,
            'judokas_count' => $poule->aantal_judokas,
            'aantal_judokas' => $poule->aantal_judokas,
            'aantal_wedstrijden' => $poule->aantal_wedstrijden,
            'titel' => $poule->getDisplayTitel(),
            'gewichtsklasse' => $poule->gewichtsklasse,
            'is_dynamisch' => $isDynamisch,
            'problemen' => $problemen, // DO NOT REMOVE: feeds JS warning UI
        ];
    }

    /**
     * Calculate min/max age and weight ranges for a poule
     */
    private function berekenPouleRanges(Poule $poule, int $huidigJaar): array
    {
        // Force fresh query to avoid SQLite caching issues
        // Filter afwezige judoka's - alleen aanwezigen tellen mee voor ranges
        $judokas = $poule->judokas()->get()->filter(fn($j) => $j->aanwezigheid !== 'afwezig');

        if ($judokas->isEmpty()) {
            return [
                'leeftijd_range' => '',
                'gewicht_range' => '',
            ];
        }

        $leeftijden = $judokas->map(fn($j) => $j->geboortejaar ? $huidigJaar - $j->geboortejaar : null)->filter();

        // Gewichten: gewogen > ingeschreven > gewichtsklasse
        $gewichten = $judokas->map(function($j) {
            if ($j->gewicht_gewogen > 0) return $j->gewicht_gewogen;
            if ($j->gewicht !== null) return $j->gewicht;
            // Gewichtsklasse is bijv. "-38" of "+73" - extract getal
            if ($j->gewichtsklasse && preg_match('/(\d+)/', $j->gewichtsklasse, $m)) {
                return (float) $m[1];
            }
            return null;
        })->filter();

        $leeftijdRange = '';
        $gewichtRange = '';

        if ($leeftijden->count() > 0) {
            $minL = $leeftijden->min();
            $maxL = $leeftijden->max();
            $leeftijdRange = $minL === $maxL ? "{$minL}j" : "{$minL}-{$maxL}j";
        }

        if ($gewichten->count() > 0) {
            $minG = $gewichten->min();
            $maxG = $gewichten->max();
            $gewichtRange = $minG === $maxG ? "{$minG}kg" : "{$minG}-{$maxG}kg";
        }

        return [
            'leeftijd_range' => $leeftijdRange,
            'gewicht_range' => $gewichtRange,
        ];
    }

    /**
     * Update poule titel als deze dynamisch is (variabele categorie)
     * Retourneert de (eventueel bijgewerkte) titel
     *
     * Variabele categorieën worden herkend aan:
     * - max_leeftijd_verschil > 0 of max_kg_verschil > 0 in categorie config
     */
    private function updateDynamischeTitel(Poule $poule, array $ranges): string
    {
        $titel = $poule->titel;
        $toernooi = $poule->toernooi;

        // Get category config for this pool's leeftijdsklasse
        $config = $toernooi->getAlleGewichtsklassen();
        $categorieConfig = null;

        // Find matching category config - try by label first, then by categorie_key
        foreach ($config as $key => $data) {
            if (($data['label'] ?? '') === $poule->leeftijdsklasse) {
                $categorieConfig = $data;
                break;
            }
        }

        // Fallback: search by categorie_key
        if (!$categorieConfig && $poule->categorie_key) {
            $categorieConfig = $config[$poule->categorie_key] ?? null;
        }

        // Check if this is a variable category
        $maxLftVerschil = (int) ($categorieConfig['max_leeftijd_verschil'] ?? 0);
        $maxKgVerschil = (int) ($categorieConfig['max_kg_verschil'] ?? 0);

        // Build new title based on config
        $parts = [];

        // 1. Label (optional)
        $toonLabel = $categorieConfig['toon_label_in_titel'] ?? true;
        if ($toonLabel && !empty($categorieConfig['label'])) {
            $parts[] = $categorieConfig['label'];
        }

        // 2. Gender (if not mixed)
        $geslacht = $poule->geslacht;
        if ($geslacht && $geslacht !== 'gemengd') {
            $parts[] = $geslacht;
        }

        // 3. Age range (if variable leeftijd)
        if ($maxLftVerschil > 0) {
            $leeftijdRange = $ranges['leeftijd_range'] ?? '';
            if ($leeftijdRange) {
                $parts[] = $leeftijdRange;
            }
        }

        // 4. Weight range - only for variable weight categories (max_kg_verschil > 0)
        if ($maxKgVerschil > 0) {
            $gewichtRange = $ranges['gewicht_range'] ?? '';
            if ($gewichtRange) {
                $parts[] = $gewichtRange;
            }
        }

        $nieuweTitel = implode(' ', $parts) ?: $titel;

        // Update database if title changed
        if ($nieuweTitel !== $titel) {
            $poule->update(['titel' => $nieuweTitel]);
        }

        return $nieuweTitel;
    }
}
