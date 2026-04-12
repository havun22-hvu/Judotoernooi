<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use App\Services\CategorieClassifier;
use App\Services\PouleIndelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Handles poule generation and verification.
 */
class PouleGeneratieController extends Controller
{
    public function __construct(
        private PouleIndelingService $pouleService,
        private WedstrijdSchemaService $wedstrijdService
    ) {}

    public function genereer(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        // Block if category problems exist
        $errors = [];

        // Check for uncategorized judokas
        $nietGecategoriseerd = $toernooi->countNietGecategoriseerd();
        if ($nietGecategoriseerd > 0) {
            $errors[] = "{$nietGecategoriseerd} judoka('s) zijn niet gecategoriseerd.";
        }

        // Check for overlapping categories
        $config = $toernooi->gewichtsklassen ?? [];
        if (!empty($config)) {
            $classifier = new CategorieClassifier($config);
            $overlaps = $classifier->detectOverlap();
            if (!empty($overlaps)) {
                $errors[] = 'Er zijn overlappende categorieën.';
            }
        }

        if (!empty($errors)) {
            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('error', 'Kan geen poule-indeling genereren: ' . implode(' ', $errors) . ' Pas eerst de categorie-instellingen aan.');
        }

        // Generate pool division (herberkenKlassen() handles sort fields)
        $statistieken = $this->pouleService->genereerPouleIndeling($toernooi);

        ActivityLogger::log($toernooi, 'genereer_poules', "Poule-indeling gegenereerd: {$statistieken['totaal_poules']} poules, {$statistieken['totaal_wedstrijden']} wedstrijden", [
            'model_type' => 'Toernooi',
            'model_id' => $toernooi->id,
            'properties' => ['totaal_poules' => $statistieken['totaal_poules'], 'totaal_wedstrijden' => $statistieken['totaal_wedstrijden']],
        ]);

        $message = "Poule-indeling gegenereerd: {$statistieken['totaal_poules']} poules, " .
                   "{$statistieken['totaal_wedstrijden']} wedstrijden.";

        $redirect = redirect()->route('toernooi.poule.index', $toernooi->routeParams());

        // Check for warnings about elimination participant counts
        $waarschuwingen = $statistieken['waarschuwingen'] ?? [];
        if (!empty($waarschuwingen)) {
            $errorMessages = [];
            $warningMessages = [];

            foreach ($waarschuwingen as $w) {
                if ($w['type'] === 'error') {
                    $errorMessages[] = $w['bericht'];
                } else {
                    $warningMessages[] = $w['bericht'];
                }
            }

            if (!empty($errorMessages)) {
                return $redirect
                    ->with('success', $message)
                    ->with('error', implode(' ', $errorMessages));
            }

            if (!empty($warningMessages)) {
                return $redirect
                    ->with('success', $message)
                    ->with('warning', implode(' ', $warningMessages));
            }
        }

        return $redirect->with('success', $message);
    }

    /**
     * Verify all poules and recalculate match counts
     */
    public function verifieer(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        $poules = $toernooi->poules()->with('judokas')->withCount('judokas')->get();
        $problemen = [];
        $totaalWedstrijden = 0;
        $herberekend = 0;
        $tolerantie = $toernooi->weging_tolerantie ?? 0.5;

        foreach ($poules as $poule) {
            $aantalJudokas = $poule->judokas_count;
            $verwachtWedstrijden = $aantalJudokas >= 2 ? ($aantalJudokas * ($aantalJudokas - 1)) / 2 : 0;

            // Check for problems (empty poules are ok)
            // Skip eliminatie and kruisfinale - they have different size requirements
            $isEliminatie = $poule->type === 'eliminatie';
            $isKruisfinale = $poule->isKruisfinale();

            if ($isEliminatie) {
                // Eliminatie needs at least 8 judokas
                if ($aantalJudokas > 0 && $aantalJudokas < 8) {
                    $problemen[] = [
                        'poule_id' => $poule->id,
                        'poule' => $poule->getDisplayTitel(),
                        'type' => 'te_weinig',
                        'message' => "#{$poule->nummer} {$poule->leeftijdsklasse} / {$poule->gewichtsklasse} kg: {$aantalJudokas} judoka's (min. 8 voor eliminatie)",
                    ];
                }
            } elseif (!$isKruisfinale) {
                // Regular poules: 3-6 judokas
                if ($aantalJudokas > 0 && $aantalJudokas < 3) {
                    $problemen[] = [
                        'poule_id' => $poule->id,
                        'poule' => $poule->getDisplayTitel(),
                        'type' => 'te_weinig',
                        'message' => "#{$poule->nummer} {$poule->leeftijdsklasse} / {$poule->gewichtsklasse} kg: {$aantalJudokas} judoka's (min. 3)",
                    ];
                } elseif ($aantalJudokas > 6) {
                    $problemen[] = [
                        'poule_id' => $poule->id,
                        'poule' => $poule->getDisplayTitel(),
                        'type' => 'te_veel',
                        'message' => "#{$poule->nummer} {$poule->leeftijdsklasse} / {$poule->gewichtsklasse} kg: {$aantalJudokas} judoka's (max. 6)",
                    ];
                }
            }
            // Kruisfinale: no size restrictions

            // Check judoka weight and category fit
            if ($aantalJudokas > 0) {
                $isDynamisch = $poule->isDynamisch();
                $pouleCategorieKey = $poule->categorie_key;

                foreach ($poule->judokas as $judoka) {
                    $pouleTitel = "#{$poule->nummer} " . $poule->getDisplayTitel();

                    // Weight check: fixed classes only (variable uses range check via isProblematischNaWeging)
                    if (!$isDynamisch && $judoka->gewicht) {
                        if (!$judoka->isGewichtBinnenKlasse($judoka->gewicht, $tolerantie, $poule->gewichtsklasse)) {
                            $problemen[] = [
                                'poule_id' => $poule->id,
                                'poule' => $poule->getDisplayTitel(),
                                'type' => 'gewicht',
                                'message' => "{$judoka->naam} ({$judoka->gewicht}kg) past niet in {$pouleTitel} ({$poule->gewichtsklasse}kg)",
                            ];
                        }
                    }

                    // Category check: judoka's categorie_key must match poule's
                    if ($pouleCategorieKey && $judoka->categorie_key && $judoka->categorie_key !== $pouleCategorieKey) {
                        $problemen[] = [
                            'poule_id' => $poule->id,
                            'poule' => $poule->getDisplayTitel(),
                            'type' => 'categorie',
                            'message' => "{$judoka->naam} ({$judoka->leeftijdsklasse}) zit in verkeerde categorie {$pouleTitel}",
                        ];
                    }
                }

                // Dynamic weight range check
                if ($isDynamisch) {
                    $probleem = $poule->isProblematischNaWeging();
                    if ($probleem) {
                        $problemen[] = [
                            'poule_id' => $poule->id,
                            'poule' => $poule->getDisplayTitel(),
                            'type' => 'gewicht_range',
                            'message' => "#{$poule->nummer} " . $poule->getDisplayTitel() . ": gewichtsverschil {$probleem['range']}kg (max {$probleem['max_toegestaan']}kg)",
                        ];
                    }
                }
            }

            // Check and fix match count (only for already activated poules)
            $huidigWedstrijden = $poule->wedstrijden()->count();
            if ($huidigWedstrijden > 0 && $huidigWedstrijden !== $verwachtWedstrijden) {
                // Regenerate matches
                $poule->wedstrijden()->delete();
                $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
                $poule->updateStatistieken();
                $herberekend++;
            }

            $totaalWedstrijden += $verwachtWedstrijden;
        }

        return response()->json([
            'success' => true,
            'totaal_poules' => $poules->count(),
            'totaal_wedstrijden' => $totaalWedstrijden,
            'herberekend' => $herberekend,
            'problemen' => $problemen,
        ]);
    }
}
