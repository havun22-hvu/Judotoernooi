<?php

namespace App\Services;

use App\Enums\Leeftijdsklasse;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PouleIndelingService
{
    private int $minJudokas;
    private int $maxJudokas;
    private array $voorkeur;
    private bool $clubspreiding;
    private array $prioriteiten;

    /**
     * Initialize with tournament-specific settings
     */
    public function initializeFromToernooi(Toernooi $toernooi): void
    {
        $this->voorkeur = $toernooi->getPouleGrootteVoorkeurOfDefault();
        // Min/max are derived from preference list
        $this->minJudokas = $toernooi->min_judokas_poule;
        $this->maxJudokas = $toernooi->max_judokas_poule;
        $this->clubspreiding = $toernooi->clubspreiding ?? true;
        $this->prioriteiten = $toernooi->verdeling_prioriteiten ?? ['groepsgrootte', 'bandkleur', 'clubspreiding'];
    }

    public function __construct()
    {
        // Default values, will be overridden by initializeFromToernooi
        $this->voorkeur = [5, 4, 6, 3];
        $this->minJudokas = 3;
        $this->maxJudokas = 6;
        $this->clubspreiding = true;
        $this->prioriteiten = ['groepsgrootte', 'bandkleur', 'clubspreiding'];
    }

    /**
     * Generate pool division for a tournament
     */
    public function genereerPouleIndeling(Toernooi $toernooi): array
    {
        // Initialize settings from tournament
        $this->initializeFromToernooi($toernooi);

        return DB::transaction(function () use ($toernooi) {
            // Delete existing pools
            $toernooi->poules()->delete();

            // Get all judokas grouped by category
            $groepen = $this->groepeerJudokas($toernooi);

            // Get wedstrijd_systeem settings
            $wedstrijdSysteem = $toernooi->wedstrijd_systeem ?? [];

            $pouleNummer = 1;
            $statistieken = [
                'totaal_poules' => 0,
                'totaal_wedstrijden' => 0,
                'totaal_kruisfinales' => 0,
                'per_leeftijdsklasse' => [],
            ];

            // Track voorrondepoules per categorie (leeftijdsklasse + gewichtsklasse) for kruisfinale creation
            $voorrondesPerCategorie = [];

            foreach ($groepen as $sleutel => $judokas) {
                if ($judokas->isEmpty()) continue;

                // Parse group key: "Leeftijdsklasse|Gewichtsklasse" or "Leeftijdsklasse|Gewichtsklasse|Geslacht"
                $delen = explode('|', $sleutel);
                $leeftijdsklasse = $delen[0];
                $gewichtsklasse = $delen[1] ?? 'Onbekend';
                $geslacht = $delen[2] ?? null;

                // Split into optimal pools
                $pouleVerdelingen = $this->maakOptimalePoules($judokas);

                foreach ($pouleVerdelingen as $pouleJudokas) {
                    $titel = $this->maakPouleTitel($leeftijdsklasse, $gewichtsklasse, $geslacht, $pouleNummer);

                    $poule = Poule::create([
                        'toernooi_id' => $toernooi->id,
                        'nummer' => $pouleNummer,
                        'titel' => $titel,
                        'type' => 'voorronde',
                        'leeftijdsklasse' => $leeftijdsklasse,
                        'gewichtsklasse' => $gewichtsklasse,
                        'aantal_judokas' => count($pouleJudokas),
                    ]);

                    // Attach judokas to pool
                    $positie = 1;
                    foreach ($pouleJudokas as $judoka) {
                        $poule->judokas()->attach($judoka->id, ['positie' => $positie++]);
                    }

                    // Calculate matches
                    $poule->updateStatistieken();

                    $statistieken['totaal_poules']++;
                    $statistieken['totaal_wedstrijden'] += $poule->aantal_wedstrijden;

                    if (!isset($statistieken['per_leeftijdsklasse'][$leeftijdsklasse])) {
                        $statistieken['per_leeftijdsklasse'][$leeftijdsklasse] = [
                            'poules' => 0,
                            'wedstrijden' => 0,
                            'kruisfinales' => 0,
                        ];
                    }
                    $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['poules']++;
                    $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['wedstrijden'] += $poule->aantal_wedstrijden;

                    // Track for kruisfinale per categorie (leeftijdsklasse + gewichtsklasse + geslacht)
                    $categorieKey = $sleutel;
                    if (!isset($voorrondesPerCategorie[$categorieKey])) {
                        $voorrondesPerCategorie[$categorieKey] = [
                            'leeftijdsklasse' => $leeftijdsklasse,
                            'gewichtsklasse' => $gewichtsklasse,
                            'geslacht' => $geslacht,
                            'aantal_poules' => 0,
                        ];
                    }
                    $voorrondesPerCategorie[$categorieKey]['aantal_poules']++;

                    $pouleNummer++;
                }
            }

            // Create kruisfinale pools where applicable (per gewichtsklasse)
            foreach ($voorrondesPerCategorie as $categorieKey => $data) {
                $klasseKey = $this->getLeeftijdsklasseKey($data['leeftijdsklasse']);
                $systeem = $wedstrijdSysteem[$klasseKey] ?? 'poules';

                // Only create kruisfinale if system is poules_kruisfinale AND there are 2+ voorrondepoules
                if ($systeem === 'poules_kruisfinale' && $data['aantal_poules'] >= 2) {
                    // Calculate how many places qualify based on number of poules
                    // Goal: kruisfinale of 4-6 judokas
                    $aantalPoules = $data['aantal_poules'];
                    $kruisfinalesAantal = $this->berekenKruisfinalesPlaatsen($aantalPoules);
                    $aantalJudokasKruisfinale = $aantalPoules * $kruisfinalesAantal;

                    $geslachtLabel = match ($data['geslacht']) {
                        'M' => 'Jongens',
                        'V' => 'Meisjes',
                        default => null,
                    };

                    // Include qualifying places in title
                    $plaatsenTekst = $kruisfinalesAantal === 1 ? 'top 1' : "top {$kruisfinalesAantal}";
                    $titel = $geslachtLabel
                        ? "Kruisfinale {$data['leeftijdsklasse']} {$geslachtLabel} {$data['gewichtsklasse']} ({$plaatsenTekst})"
                        : "Kruisfinale {$data['leeftijdsklasse']} {$data['gewichtsklasse']} ({$plaatsenTekst})";

                    $kruisfinalePoule = Poule::create([
                        'toernooi_id' => $toernooi->id,
                        'nummer' => $pouleNummer,
                        'titel' => $titel,
                        'type' => 'kruisfinale',
                        'kruisfinale_plaatsen' => $kruisfinalesAantal,
                        'leeftijdsklasse' => $data['leeftijdsklasse'],
                        'gewichtsklasse' => $data['gewichtsklasse'],
                        'aantal_judokas' => $aantalJudokasKruisfinale,
                        'aantal_wedstrijden' => $this->berekenAantalWedstrijden($aantalJudokasKruisfinale),
                    ]);

                    $statistieken['totaal_poules']++;
                    $statistieken['totaal_kruisfinales']++;
                    $statistieken['totaal_wedstrijden'] += $kruisfinalePoule->aantal_wedstrijden;
                    $statistieken['per_leeftijdsklasse'][$data['leeftijdsklasse']]['kruisfinales']++;
                    $statistieken['per_leeftijdsklasse'][$data['leeftijdsklasse']]['wedstrijden'] += $kruisfinalePoule->aantal_wedstrijden;

                    $pouleNummer++;
                }
            }

            $toernooi->update(['poules_gegenereerd_op' => now()]);

            return $statistieken;
        });
    }

    /**
     * Get the key used in wedstrijd_systeem for a leeftijdsklasse label
     */
    private function getLeeftijdsklasseKey(string $label): string
    {
        $mapping = [
            "Mini's" => 'minis',
            'A-pupillen' => 'a_pupillen',
            'B-pupillen' => 'b_pupillen',
            'Dames -15' => 'dames_15',
            'Heren -15' => 'heren_15',
            'Dames -18' => 'dames_18',
            'Heren -18' => 'heren_18',
            'Dames' => 'dames',
            'Heren' => 'heren',
        ];

        return $mapping[$label] ?? strtolower(str_replace([' ', '-', "'"], '_', $label));
    }

    /**
     * Calculate how many places qualify for kruisfinale based on number of poules
     * Goal: kruisfinale of 4-6 judokas (ideal pool size)
     *
     * 2 poules → top 2 or 3 (4-6 judokas)
     * 3 poules → top 2 (6 judokas)
     * 4 poules → top 1 (4 judokas) or top 2 if we want more
     * 5+ poules → top 1 (5+ judokas)
     */
    private function berekenKruisfinalesPlaatsen(int $aantalPoules): int
    {
        if ($aantalPoules <= 2) {
            return 3; // 2 poules × 3 = 6 judokas
        }
        if ($aantalPoules === 3) {
            return 2; // 3 poules × 2 = 6 judokas
        }
        if ($aantalPoules <= 5) {
            return 1; // 4-5 poules × 1 = 4-5 judokas
        }
        // 6+ poules: still top 1, results in 6+ judokas kruisfinale
        return 1;
    }

    /**
     * Calculate number of matches for a given number of judokas
     */
    private function berekenAantalWedstrijden(int $aantal): int
    {
        if ($aantal <= 1) return 0;
        if ($aantal === 3) return 6; // Double round
        return intval(($aantal * ($aantal - 1)) / 2);
    }

    /**
     * Group judokas by age class, weight class, and (for U15+) gender
     * Sorted by judoka_code (leeftijd → gewicht → band) for correct ordering
     */
    private function groepeerJudokas(Toernooi $toernooi): Collection
    {
        // Sort by judoka_code: leeftijd → gewicht → band → geslacht
        $judokas = $toernooi->judokas()
            ->orderBy('judoka_code')
            ->get();

        $groepen = $judokas->groupBy(function (Judoka $judoka) {
            $leeftijdsklasse = $judoka->leeftijdsklasse ?: 'Onbekend';
            $gewichtsklasse = $judoka->gewichtsklasse ?: 'Onbekend';
            $geslacht = strtoupper($judoka->geslacht);

            // For U15, U18, U21 and Senioren: separate by gender
            $oudereCategorien = ['U15', 'U18', 'U21', 'Senioren'];
            if (in_array($leeftijdsklasse, $oudereCategorien)) {
                return "{$leeftijdsklasse}|{$gewichtsklasse}|{$geslacht}";
            }

            // For younger categories (U9, U11, U13): mixed gender
            return "{$leeftijdsklasse}|{$gewichtsklasse}";
        });

        // Sort groups by leeftijd order, then gewicht
        return $groepen->sortBy(function ($judokas, $key) {
            $delen = explode('|', $key);
            $leeftijd = $delen[0] ?? '';
            $gewicht = $delen[1] ?? '';

            // Leeftijd order: Mini's=1, A-pup=2, B-pup=3, etc.
            $leeftijdOrder = $this->getLeeftijdOrder($leeftijd);

            // Gewicht: numeriek sorteren
            $gewichtNum = intval(preg_replace('/[^0-9]/', '', $gewicht));
            $gewichtPlus = str_starts_with($gewicht, '+') ? 1000 : 0;

            return sprintf('%02d%04d', $leeftijdOrder, $gewichtNum + $gewichtPlus);
        });
    }

    /**
     * Get sort order for leeftijdsklasse
     */
    private function getLeeftijdOrder(string $leeftijd): int
    {
        $order = [
            "Mini's" => 1,
            'A-pupillen' => 2,
            'B-pupillen' => 3,
            'C-pupillen' => 4,
            'Dames -15' => 5,
            'Heren -15' => 6,
            'Dames -18' => 7,
            'Heren -18' => 8,
            'Dames -21' => 9,
            'Heren -21' => 10,
            'Dames' => 11,
            'Heren' => 12,
        ];

        return $order[$leeftijd] ?? 99;
    }

    /**
     * Create optimal pool division based on preference order
     * Uses the configured preference list (e.g., [5, 4, 6, 3]) to score divisions
     */
    private function maakOptimalePoules(Collection $judokas): array
    {
        $aantal = $judokas->count();
        $judokasArray = $judokas->values()->all();

        // Less than minimum: single pool (can't split)
        if ($aantal <= $this->minJudokas) {
            return [$judokasArray];
        }

        // If within max, check if single pool is best
        if ($aantal <= $this->maxJudokas) {
            return [$judokasArray];
        }

        // Find best division based on preference scores
        $bestePouleGroottes = [];
        $besteScore = PHP_INT_MAX;

        $maxPoules = (int) floor($aantal / $this->minJudokas);

        for ($aantalPoules = 2; $aantalPoules <= $maxPoules; $aantalPoules++) {
            $basisGrootte = (int) floor($aantal / $aantalPoules);
            $rest = $aantal % $aantalPoules;

            // Calculate pool sizes for this division
            $pouleGroottes = array_fill(0, $aantalPoules, $basisGrootte);
            for ($i = 0; $i < $rest; $i++) {
                $pouleGroottes[$i]++;
            }

            // Skip if any pool is outside min/max bounds
            $valid = true;
            foreach ($pouleGroottes as $grootte) {
                if ($grootte < $this->minJudokas || $grootte > $this->maxJudokas) {
                    $valid = false;
                    break;
                }
            }
            if (!$valid) continue;

            // Calculate score based on preference order
            $score = $this->berekenVerdelingScore($pouleGroottes);

            if ($score < $besteScore) {
                $besteScore = $score;
                $bestePouleGroottes = $pouleGroottes;
            }
        }

        if (empty($bestePouleGroottes)) {
            return [$judokasArray];
        }

        // Distribute by slicing (preserves band order from judoka_code sorting)
        $verdeling = [];
        $index = 0;
        foreach ($bestePouleGroottes as $grootte) {
            $verdeling[] = array_slice($judokasArray, $index, $grootte);
            $index += $grootte;
        }

        // Apply club spreading as refinement
        // Check priority: if clubspreiding > bandkleur, allow cross-band swaps
        if ($this->clubspreiding && count($verdeling) > 1) {
            $bandkleurIdx = array_search('bandkleur', $this->prioriteiten);
            $clubspreidingIdx = array_search('clubspreiding', $this->prioriteiten);

            // If clubspreiding has higher priority (lower index), allow any swap
            // If bandkleur has higher priority, only swap same band
            $onlySwapSameBand = ($bandkleurIdx !== false && $clubspreidingIdx !== false)
                ? $bandkleurIdx < $clubspreidingIdx
                : true;

            $verdeling = $this->pasClubspreidingToe($verdeling, $onlySwapSameBand);
        }

        return $verdeling;
    }

    /**
     * Apply club spreading as refinement
     * @param bool $onlySwapSameBand If true, only swap judokas with same band
     */
    private function pasClubspreidingToe(array $poules, bool $onlySwapSameBand = true): array
    {
        $aantalPoules = count($poules);

        // For each pool, check for club duplicates
        for ($p = 0; $p < $aantalPoules; $p++) {
            $clubCount = [];
            foreach ($poules[$p] as $idx => $judoka) {
                $clubId = $judoka->club_id ?? 0;
                if (!isset($clubCount[$clubId])) {
                    $clubCount[$clubId] = [];
                }
                $clubCount[$clubId][] = $idx;
            }

            // For clubs with multiple judokas, try to swap one to another pool
            foreach ($clubCount as $clubId => $indices) {
                if (count($indices) <= 1) continue;

                // Try to swap the second (and further) judoka(s) to other pools
                for ($i = 1; $i < count($indices); $i++) {
                    $judokaIdx = $indices[$i];
                    $judoka = $poules[$p][$judokaIdx];
                    $judokaBand = $judoka->band;

                    // Find a swap candidate in another pool
                    for ($q = 0; $q < $aantalPoules; $q++) {
                        if ($q === $p) continue;

                        foreach ($poules[$q] as $kandidaatIdx => $kandidaat) {
                            // Check band compatibility
                            $bandMatch = !$onlySwapSameBand || $kandidaat->band === $judokaBand;

                            // Different club, that club is not already in target pool
                            if ($bandMatch &&
                                $kandidaat->club_id !== $clubId &&
                                !$this->clubInPoule($poules[$p], $kandidaat->club_id, $judokaIdx)) {

                                // Check if the kandidaat's club is not duplicated in their pool
                                if (!$this->clubInPoule($poules[$q], $judoka->club_id, $kandidaatIdx)) {
                                    // Swap
                                    $poules[$p][$judokaIdx] = $kandidaat;
                                    $poules[$q][$kandidaatIdx] = $judoka;
                                    break 2; // Move to next duplicate
                                }
                            }
                        }
                    }
                }
            }
        }

        return $poules;
    }

    /**
     * Check if a club is already in a pool (excluding a specific index)
     */
    private function clubInPoule(array $poule, ?int $clubId, int $excludeIdx): bool
    {
        foreach ($poule as $idx => $judoka) {
            if ($idx !== $excludeIdx && ($judoka->club_id ?? 0) === $clubId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate score for a division based on preference order
     * Lower score = better division
     */
    private function berekenVerdelingScore(array $pouleGroottes): int
    {
        $score = 0;

        foreach ($pouleGroottes as $grootte) {
            // Find position in preference list (0 = best)
            $positie = array_search($grootte, $this->voorkeur);

            if ($positie === false) {
                // Size not in preference list - heavy penalty
                $score += 1000;
            } else {
                // Score based on position: first preference = 0, second = 10, etc.
                $score += $positie * 10;
            }
        }

        return $score;
    }

    /**
     * Create standardized pool title
     */
    private function maakPouleTitel(string $leeftijdsklasse, string $gewichtsklasse, ?string $geslacht, int $pouleNr): string
    {
        $lk = $leeftijdsklasse ?: 'Onbekend';
        $gk = $gewichtsklasse ?: 'Onbekend gewicht';

        // Format weight class
        if (!str_contains($gk, 'kg')) {
            $gk .= ' kg';
        }

        // Add gender for older categories
        $geslachtLabel = match ($geslacht) {
            'M' => 'Jongens',
            'V' => 'Meisjes',
            default => null,
        };

        if ($geslachtLabel) {
            return "{$lk} {$geslachtLabel} {$gk} Poule {$pouleNr}";
        }

        return "{$lk} {$gk} Poule {$pouleNr}";
    }

    /**
     * Move judoka to different pool
     */
    public function verplaatsJudoka(Judoka $judoka, Poule $nieuwePoule): void
    {
        DB::transaction(function () use ($judoka, $nieuwePoule) {
            // Remove from current pool(s)
            $huidigePoules = $judoka->poules;
            foreach ($huidigePoules as $poule) {
                $poule->judokas()->detach($judoka->id);
                $poule->updateStatistieken();
            }

            // Add to new pool
            $positie = $nieuwePoule->judokas()->count() + 1;
            $nieuwePoule->judokas()->attach($judoka->id, ['positie' => $positie]);
            $nieuwePoule->updateStatistieken();

            // Update judoka's weight class if needed
            if ($judoka->gewichtsklasse !== $nieuwePoule->gewichtsklasse) {
                $judoka->update(['gewichtsklasse' => $nieuwePoule->gewichtsklasse]);
            }
        });
    }

    /**
     * Calculate total matches for tournament
     */
    public function berekenTotaalWedstrijden(Toernooi $toernooi): int
    {
        return $toernooi->poules()->sum('aantal_wedstrijden');
    }

    /**
     * Recalculate all judoka codes for tournament
     * Orders by leeftijdsklasse, gewichtsklasse, band (descending), naam
     */
    public function herberekenJudokaCodes(Toernooi $toernooi): int
    {
        $judokas = $toernooi->judokas()
            ->orderBy('leeftijdsklasse')
            ->orderBy('gewichtsklasse')
            ->orderByRaw("CASE geslacht WHEN 'M' THEN 1 WHEN 'V' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE band
                WHEN 'zwart' THEN 0
                WHEN 'bruin' THEN 1
                WHEN 'blauw' THEN 2
                WHEN 'groen' THEN 3
                WHEN 'oranje' THEN 4
                WHEN 'geel' THEN 5
                WHEN 'wit' THEN 6
                ELSE 7 END")
            ->orderBy('naam')
            ->get();

        $vorigeCategorie = null;
        $volgnummer = 0;
        $bijgewerkt = 0;

        foreach ($judokas as $judoka) {
            // Create category key for volgnummer reset
            $categorie = "{$judoka->leeftijdsklasse}|{$judoka->gewichtsklasse}|{$judoka->geslacht}";

            if ($categorie !== $vorigeCategorie) {
                $volgnummer = 1;
                $vorigeCategorie = $categorie;
            } else {
                $volgnummer++;
            }

            $nieuweCode = $judoka->berekenJudokaCode($volgnummer);
            if ($judoka->judoka_code !== $nieuweCode) {
                $judoka->update(['judoka_code' => $nieuweCode]);
                $bijgewerkt++;
            }
        }

        return $bijgewerkt;
    }
}
