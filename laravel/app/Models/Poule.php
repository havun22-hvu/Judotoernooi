<?php

namespace App\Models;

use App\Services\CategorieClassifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poule extends Model
{
    use HasFactory;

    protected $fillable = [
        'toernooi_id',
        'blok_id',
        'mat_id',
        'nummer',
        'titel',
        'type', // voorronde, kruisfinale
        'kruisfinale_plaatsen', // how many places qualify (1, 2, 3)
        'leeftijdsklasse',
        'gewichtsklasse',
        'categorie_key', // For grouping in block distribution (e.g., "m_variabel", "beginners")
        'barrage_van_poule_id', // Link naar originele poule voor barrage
        'aantal_judokas',
        'aantal_wedstrijden',
        'spreker_klaar',
        'afgeroepen_at',
        'huidige_wedstrijd_id', // Manual override for next match (yellow)
        'actieve_wedstrijd_id', // Currently playing match (green)
        'doorgestuurd_op',
    ];

    protected $casts = [
        'spreker_klaar' => 'datetime',
        'afgeroepen_at' => 'datetime',
        'doorgestuurd_op' => 'datetime',
    ];

    public function isKruisfinale(): bool
    {
        return $this->type === 'kruisfinale';
    }

    public function isVoorronde(): bool
    {
        return $this->type === 'voorronde' || $this->type === null;
    }

    public function isBarrage(): bool
    {
        return $this->type === 'barrage';
    }

    /**
     * Check if this poule uses the punten competitie system.
     */
    public function isPuntenCompetitie(): bool
    {
        $systeem = $this->toernooi?->wedstrijd_systeem[$this->categorie_key] ?? 'poules';
        return $systeem === 'punten_competitie';
    }

    /**
     * Get the number of matches per judoka for punten competitie (default 4).
     */
    public function getPuntenCompetitieWedstrijden(): int
    {
        return (int) ($this->toernooi?->punten_competitie_wedstrijden[$this->categorie_key] ?? 4);
    }

    /**
     * Originele poule waar deze barrage bij hoort
     */
    public function originelePoule(): BelongsTo
    {
        return $this->belongsTo(Poule::class, 'barrage_van_poule_id');
    }

    /**
     * Get the voorrondepoules that feed into this kruisfinale
     */
    public function getVoorrondePoules()
    {
        if (!$this->isKruisfinale()) {
            return collect();
        }

        return static::where('toernooi_id', $this->toernooi_id)
            ->where('leeftijdsklasse', $this->leeftijdsklasse)
            ->where('type', 'voorronde')
            ->get();
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class);
    }

    public function mat(): BelongsTo
    {
        return $this->belongsTo(Mat::class);
    }

    public function judokas(): BelongsToMany
    {
        return $this->belongsToMany(Judoka::class, 'poule_judoka')
            ->withPivot(['positie', 'punten', 'gewonnen', 'verloren', 'gelijk', 'eindpositie'])
            ->withTimestamps()
            ->orderBy('poule_judoka.positie');
    }

    /**
     * Add judoka to poule and update statistics
     */
    public function voegJudokaToe(Judoka $judoka, ?int $positie = null): void
    {
        $positie = $positie ?? ($this->judokas()->count() + 1);
        $this->judokas()->attach($judoka->id, ['positie' => $positie]);
        $this->updateStatistieken();
    }

    /**
     * Remove judoka from poule and update statistics
     */
    public function verwijderJudoka(Judoka $judoka): void
    {
        $this->judokas()->detach($judoka->id);
        $this->updateStatistieken();
    }

    public function wedstrijden(): HasMany
    {
        return $this->hasMany(Wedstrijd::class)->orderBy('volgorde');
    }

    /**
     * Calculate number of matches for a given number of judokas
     * Formula depends on poule type:
     * - voorronde/kruisfinale: n*(n-1)/2 for round-robin, doubled if dubbel spel enabled
     * - eliminatie: double elimination with repechage
     */
    public function berekenAantalWedstrijden(?int $aantalJudokas = null): int
    {
        $aantal = $aantalJudokas ?? $this->aantal_judokas;

        // Elimination bracket calculation
        if ($this->type === 'eliminatie') {
            return $this->berekenEliminatieWedstrijden($aantal);
        }

        // Punten competitie: fixed number of matches per judoka
        if ($this->isPuntenCompetitie() && $aantal >= 2) {
            $wedstrijdenPerJudoka = $this->getPuntenCompetitieWedstrijden();
            return (int) ceil(($aantal * $wedstrijdenPerJudoka) / 2);
        }

        // Round-robin calculation
        $enkelRonde = intval(($aantal * ($aantal - 1)) / 2);

        // Get toernooi settings
        $bestOfThreeBij2 = $this->toernooi?->best_of_three_bij_2 ?? false;
        $dubbelBij2 = $this->toernooi?->dubbel_bij_2_judokas ?? true;
        $dubbelBij3 = $this->toernooi?->dubbel_bij_3_judokas ?? true;
        $dubbelBij4 = $this->toernooi?->dubbel_bij_4_judokas ?? false;

        // Best of Three overrides dubbel for 2 judokas
        if ($aantal === 2 && $bestOfThreeBij2) return 3;
        if ($aantal === 2 && $dubbelBij2) return $enkelRonde * 2; // 2
        if ($aantal === 3 && $dubbelBij3) return $enkelRonde * 2; // 6
        if ($aantal === 4 && $dubbelBij4) return $enkelRonde * 2; // 12

        return $enkelRonde;
    }

    /**
     * Calculate number of matches for elimination bracket
     *
     * Formulas (see docs/2-FEATURES/ELIMINATIE/FORMULES.md):
     * - 2 brons: 2N - 5
     * - 1 brons: 2N - 4
     */
    private function berekenEliminatieWedstrijden(int $aantal): int
    {
        if ($aantal < 2) return 0;

        // Lees aantal_brons uit toernooi (default 2)
        $aantalBrons = $this->toernooi?->aantal_brons ?? 2;

        // Formule: 2N-5 (dubbel brons) of 2N-4 (enkel brons)
        return (2 * $aantal) - ($aantalBrons == 2 ? 5 : 4);
    }

    /**
     * Update statistics: count all judokas in poule
     *
     * Note: Filtering (absent, wrong weight class) is handled by MOVING judokas
     * out of the poule, not by filtering during count. Who is in the poule, counts.
     */
    public function updateStatistieken(): void
    {
        $this->unsetRelation('judokas');

        $aantalJudokas = $this->judokas()->count();

        // Voor kruisfinale/eliminatie zonder fysieke judokas: behoud bestaand aantal
        // (berekend uit kruisfinale_plaatsen × aantal voorronde poules)
        if ($aantalJudokas === 0 && $this->aantal_judokas > 0 && in_array($this->type, ['kruisfinale', 'eliminatie'])) {
            $aantalJudokas = $this->aantal_judokas;
        } else {
            $this->aantal_judokas = $aantalJudokas;
        }

        $this->aantal_wedstrijden = $this->berekenAantalWedstrijden($aantalJudokas);

        // Update titel met actuele gewichtsrange (voor dynamische categorieën)
        $this->updateTitel();

        $this->save();
    }

    /**
     * Update poule titel met actuele leeftijds- en gewichtsrange
     * Alleen voor dynamische poules (max_kg_verschil > 0)
     * Vaste klassen (max_kg_verschil = 0) behouden hun titel
     */
    public function updateTitel(): void
    {
        // Skip voor vaste gewichtsklassen (max_kg_verschil = 0)
        if (!$this->isDynamisch()) {
            return;
        }

        // Haal label uit leeftijdsklasse (bijv. "Mini's" uit "Mini's 4-5j")
        $label = $this->leeftijdsklasse ?? '';
        $label = trim(preg_replace('/\s*\d+-\d+j\s*/', '', $label));

        // Bereken leeftijdsrange uit judoka's
        $leeftijdRange = $this->getLeeftijdsRange();
        $leeftijdStr = '';
        if ($leeftijdRange) {
            if ($leeftijdRange['min_jaar'] === $leeftijdRange['max_jaar']) {
                $leeftijdStr = $leeftijdRange['min_jaar'] . 'j';
            } else {
                $leeftijdStr = $leeftijdRange['min_jaar'] . '-' . $leeftijdRange['max_jaar'] . 'j';
            }
        }

        // Bereken gewichtsrange uit judoka's
        $gewichtRange = $this->getGewichtsRange();
        $gewichtStr = '';
        if ($gewichtRange && $gewichtRange['min_kg'] !== null && $gewichtRange['max_kg'] !== null) {
            $gewichtStr = round($gewichtRange['min_kg'], 1) . '-' . round($gewichtRange['max_kg'], 1) . 'kg';
        }

        // Bouw titel: "Label leeftijdj gewichtkg"
        $parts = array_filter([$label, $leeftijdStr, $gewichtStr]);
        $this->titel = implode(' ', $parts);
    }

    /**
     * Check of poule een dynamische categorie is (max_kg_verschil > 0)
     */
    public function isDynamisch(): bool
    {
        if (!$this->categorie_key) {
            return false;
        }

        $classifier = $this->getClassifier();
        return $classifier->isDynamisch($this->categorie_key);
    }

    /**
     * Get de categorie config voor deze poule.
     * Uses CategorieClassifier with direct key lookup (never searches on label).
     */
    public function getCategorieConfig(): array
    {
        $classifier = $this->getClassifier();
        $config = $classifier->getConfigVoorPoule($this);

        if ($config) {
            return $config;
        }

        // Fallback: no config found = treat as fixed (no weight range validation)
        return [
            'max_kg_verschil' => 0,
            'max_leeftijd_verschil' => 0,
        ];
    }

    /**
     * Get CategorieClassifier instance for this poule's toernooi.
     */
    private function getClassifier(): CategorieClassifier
    {
        $gewichtsklassen = $this->toernooi?->gewichtsklassen ?? [];
        $tolerantie = $this->toernooi?->gewicht_tolerantie ?? 0.5;

        return new CategorieClassifier($gewichtsklassen, $tolerantie);
    }

    /**
     * Get display titel voor poule
     *
     * Opbouw: Label / Leeftijd / Gewicht
     * - Label: alleen als toon_label_in_titel = true in config
     * - Leeftijd: alleen als max_leeftijd_verschil > 0
     * - Gewicht: gewichtsklasse (bij kg.verschil=0) OF gewichtsrange (bij kg.verschil>0)
     */
    public function getDisplayTitel(): string
    {
        $config = $this->getCategorieConfig();
        $toonLabel = (bool) ($config['toon_label_in_titel'] ?? false);
        $maxLeeftijdVerschil = (int) ($config['max_leeftijd_verschil'] ?? 0);
        $maxKgVerschil = (float) ($config['max_kg_verschil'] ?? 0);

        $parts = [];

        // 1. Label (alleen als aangevinkt in config)
        if ($toonLabel) {
            $label = $config['label'] ?? $this->leeftijdsklasse ?? '';
            // Strip eventuele leeftijd/gewicht uit label
            $label = trim(preg_replace('/\s*\d+(-\d+)?j\s*/', '', $label));
            $label = trim(preg_replace('/\s*-?\d+kg\s*/i', '', $label));
            if ($label) {
                $parts[] = $label;
            }
        }

        // 2. Leeftijd range (alleen als max_leeftijd_verschil > 0)
        if ($maxLeeftijdVerschil > 0) {
            $leeftijdRange = $this->getLeeftijdsRange();
            if ($leeftijdRange) {
                if ($leeftijdRange['min_jaar'] === $leeftijdRange['max_jaar']) {
                    $parts[] = $leeftijdRange['min_jaar'] . 'j';
                } else {
                    $parts[] = $leeftijdRange['min_jaar'] . '-' . $leeftijdRange['max_jaar'] . 'j';
                }
            }
        }

        // 3. Gewicht
        if ($maxKgVerschil > 0) {
            // Variabel: bereken range uit judoka's
            $gewichtRange = $this->getGewichtsRange();
            if ($gewichtRange) {
                $parts[] = round($gewichtRange['min_kg'], 1) . '-' . round($gewichtRange['max_kg'], 1) . 'kg';
            }
        } elseif ($this->gewichtsklasse) {
            // Vaste klasse: gebruik opgeslagen gewichtsklasse
            $gewicht = $this->gewichtsklasse;
            if (!str_ends_with(strtolower($gewicht), 'kg')) {
                $gewicht .= 'kg';
            }
            $parts[] = $gewicht;
        }

        // Combineer met " / " separator
        if (!empty($parts)) {
            return implode(' / ', $parts);
        }

        // Fallback: gebruik titel of leeftijdsklasse
        return $this->titel ?: $this->leeftijdsklasse ?: '';
    }

    /**
     * Bereken de gewichtsrange van actieve judoka's in de poule
     * Retourneert [min_kg, max_kg, range] of null als geen gewogen judoka's
     */
    public function getGewichtsRange(): ?array
    {
        // Use gewicht_gewogen if available, otherwise fall back to gewicht (ingeschreven)
        // Na weging sluiten: alleen gewogen judoka's meetellen (gewicht_gewogen != null)
        // Voor weging: alle actieve judoka's met ingeschreven gewicht
        $blok = $this->blok;
        $wegingGesloten = $blok?->weging_gesloten ?? false;

        $query = $this->judokas()
            ->where(function ($q) {
                $q->whereNull('aanwezigheid')
                  ->orWhere('aanwezigheid', '!=', 'afwezig');
            });

        // Na weging sluiten: alleen gewogen judoka's
        if ($wegingGesloten) {
            $query->whereNotNull('gewicht_gewogen');
        }

        $gewichten = $query->get()
            ->map(fn($j) => $j->gewicht_gewogen ?? $j->gewicht)
            ->filter()
            ->values();

        if ($gewichten->isEmpty()) {
            return null;
        }

        $min = $gewichten->min();
        $max = $gewichten->max();

        return [
            'min_kg' => $min,
            'max_kg' => $max,
            'range' => $max - $min,
        ];
    }

    /**
     * Bereken de leeftijdsrange van actieve judoka's in de poule
     * Retourneert [min_jaar, max_jaar, range] of null als geen judoka's
     */
    public function getLeeftijdsRange(): ?array
    {
        $blok = $this->blok;
        $wegingGesloten = $blok?->weging_gesloten ?? false;

        $query = $this->judokas()
            ->where(function ($q) {
                $q->whereNull('aanwezigheid')
                  ->orWhere('aanwezigheid', '!=', 'afwezig');
            });

        // Na weging sluiten: alleen gewogen judoka's
        if ($wegingGesloten) {
            $query->whereNotNull('gewicht_gewogen');
        }

        $leeftijden = $query->get()
            ->map(fn($j) => $j->leeftijd)
            ->filter()
            ->values();

        if ($leeftijden->isEmpty()) {
            return null;
        }

        $min = $leeftijden->min();
        $max = $leeftijden->max();

        return [
            'min_jaar' => $min,
            'max_jaar' => $max,
            'range' => $max - $min,
        ];
    }

    /**
     * Check of poule problematisch is na weging (range > max_kg_verschil)
     * Retourneert null als niet problematisch, anders array met details
     *
     * BELANGRIJK: Alleen voor dynamische categorieën (max_kg_verschil > 0)
     * Vaste categorieën (max_kg_verschil = 0) worden NIET gecheckt op gewichtsrange
     */
    public function isProblematischNaWeging(): ?array
    {
        // Alleen voor dynamische categorieën
        if (!$this->isDynamisch()) {
            return null;
        }

        $range = $this->getGewichtsRange();
        if (!$range) {
            return null;
        }

        $config = $this->getCategorieConfig();

        // Haal max_kg_verschil uit config - GEEN fallback, want isDynamisch()
        // heeft al bevestigd dat de config max_kg_verschil > 0 heeft
        $maxKgVerschil = $config['max_kg_verschil'] ?? 0;

        // Extra check: als max_kg_verschil = 0, is dit geen dynamische categorie
        // Dit kan voorkomen bij config mismatch - behandel als niet-problematisch
        if ($maxKgVerschil <= 0) {
            return null;
        }

        if ($range['range'] <= $maxKgVerschil) {
            return null;
        }

        // Vind lichtste en zwaarste judoka
        $judokas = $this->judokas()
            ->whereNotNull('gewicht_gewogen')
            ->where(function ($q) {
                $q->whereNull('aanwezigheid')
                  ->orWhere('aanwezigheid', '!=', 'afwezig');
            })
            ->orderBy('gewicht_gewogen')
            ->get();

        return [
            'range' => $range['range'],
            'max_toegestaan' => $maxKgVerschil,
            'overschrijding' => $range['range'] - $maxKgVerschil,
            'min_kg' => $range['min_kg'],
            'max_kg' => $range['max_kg'],
            'lichtste' => $judokas->first(),
            'zwaarste' => $judokas->last(),
            'judokas' => $judokas,
        ];
    }

    /**
     * Generate match schedule for this poule
     * Returns optimal match order to minimize consecutive matches for same judoka
     * Each judoka gets rest between their matches
     */
    public function genereerWedstrijdSchema(): array
    {
        $judokas = $this->judokas->pluck('id')->toArray();
        $aantal = count($judokas);

        if ($aantal < 2) {
            return [];
        }

        // Get optimal order indices (1-based from legacy code)
        $volgorde = $this->getOptimaleWedstrijdvolgorde($aantal);

        // Convert indices to actual judoka IDs
        $wedstrijden = [];
        foreach ($volgorde as $paar) {
            $wedstrijden[] = [
                $judokas[$paar[0] - 1],  // Convert 1-based to 0-based
                $judokas[$paar[1] - 1],
            ];
        }

        return $wedstrijden;
    }

    /**
     * Get optimal match order for given number of judokas
     * Returns array of [judoka1, judoka2] pairs (1-based indices)
     * Uses tournament settings if configured, otherwise defaults
     */
    private function getOptimaleWedstrijdvolgorde(int $aantal): array
    {
        // First check if tournament has custom schemas
        $toernooi = $this->toernooi;
        $customSchemas = $toernooi?->wedstrijd_schemas ?? [];

        if (!empty($customSchemas[$aantal])) {
            return $customSchemas[$aantal];
        }

        // Default optimized schemas
        $schema = match ($aantal) {
            2 => [[1, 2], [2, 1]],
            3 => [[1, 2], [1, 3], [2, 3], [2, 1], [3, 2], [3, 1]],
            4 => [[1, 2], [3, 4], [2, 3], [1, 4], [2, 4], [1, 3]],
            5 => [[1, 2], [3, 4], [1, 5], [2, 3], [4, 5], [1, 3], [2, 4], [3, 5], [1, 4], [2, 5]],
            6 => [[1, 2], [3, 4], [5, 6], [1, 3], [2, 5], [4, 6], [3, 5], [2, 4], [1, 6], [2, 3], [4, 5], [3, 6], [1, 4], [2, 6], [1, 5]],
            7 => [[1, 2], [3, 4], [5, 6], [1, 7], [2, 3], [4, 5], [6, 7], [1, 3], [2, 4], [5, 7], [3, 6], [1, 4], [2, 5], [3, 7], [4, 6], [1, 5], [2, 6], [4, 7], [1, 6], [3, 5], [2, 7]],
            default => $this->genereerRoundRobinSchema($aantal),
        };

        return $schema;
    }

    /**
     * Generate round-robin schema using circle method
     * For 7+ judokas where predefined schemas aren't available
     */
    private function genereerRoundRobinSchema(int $n): array
    {
        $wedstrijden = [];
        $judokas = range(1, $n);

        // Add dummy for odd numbers
        if ($n % 2 !== 0) {
            $judokas[] = null;
        }

        $totaal = count($judokas);

        // Round Robin with circle method
        for ($ronde = 0; $ronde < $totaal - 1; $ronde++) {
            for ($i = 0; $i < $totaal / 2; $i++) {
                $j = $totaal - 1 - $i;

                $judoka1 = $judokas[$i];
                $judoka2 = $judokas[$j];

                // Skip if one is dummy (bye)
                if ($judoka1 !== null && $judoka2 !== null) {
                    $wedstrijden[] = [$judoka1, $judoka2];
                }
            }

            // Rotate (first position stays fixed)
            $laatste = array_pop($judokas);
            array_splice($judokas, 1, 0, [$laatste]);
        }

        return $wedstrijden;
    }
}
