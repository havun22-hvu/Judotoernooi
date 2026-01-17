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
        'aantal_judokas',
        'aantal_wedstrijden',
        'spreker_klaar',
        'afgeroepen_at',
        'huidige_wedstrijd_id', // Manual override for next match (yellow)
        'actieve_wedstrijd_id', // Currently playing match (green)
    ];

    protected $casts = [
        'spreker_klaar' => 'datetime',
        'afgeroepen_at' => 'datetime',
    ];

    public function isKruisfinale(): bool
    {
        return $this->type === 'kruisfinale';
    }

    public function isVoorronde(): bool
    {
        return $this->type === 'voorronde' || $this->type === null;
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

        // Round-robin calculation
        $enkelRonde = intval(($aantal * ($aantal - 1)) / 2);

        // Get toernooi settings
        $dubbelBij2 = $this->toernooi?->dubbel_bij_2_judokas ?? true;
        $dubbelBij3 = $this->toernooi?->dubbel_bij_3_judokas ?? true;
        $dubbelBij4 = $this->toernooi?->dubbel_bij_4_judokas ?? false;

        if ($aantal === 2 && $dubbelBij2) return $enkelRonde * 2; // 2
        if ($aantal === 3 && $dubbelBij3) return $enkelRonde * 2; // 6
        if ($aantal === 4 && $dubbelBij4) return $enkelRonde * 2; // 12

        return $enkelRonde;
    }

    /**
     * Calculate number of matches for elimination bracket
     * Double elimination with B-group (losers bracket) and 2 bronze matches
     *
     * Formulas:
     * - A-group: N - 1 (single elimination)
     * - B-group: depends on bracket size D
     *   - D=4 (5-8 judokas):    4 (B 1/2 + Brons)
     *   - D=8 (9-15 judokas):   12 (B 1/4 deel 1+2 + B 1/2 + Brons)
     *   - D=16 (16-31 judokas): max(0, N-24) + 20
     *   - D=32 (32-63 judokas): 28 (fixed structure)
     */
    private function berekenEliminatieWedstrijden(int $aantal): int
    {
        if ($aantal < 2) return 0;
        if ($aantal === 2) return 1; // Just finale
        if ($aantal === 3) return 4; // 2 A + 2 B (simplified)
        if ($aantal === 4) return 7; // 3 A + 2 B 1/2 + 2 brons

        // A-group: always N - 1 (single elimination)
        $aWedstrijden = $aantal - 1;

        // Calculate D = largest power of 2 <= N
        $d = 1;
        while ($d * 2 <= $aantal) {
            $d *= 2;
        }

        // B-group calculation based on D
        if ($d >= 32) {
            // D=32+: fixed B structure
            // B 1/8 deel 1 + B 1/8 deel 2 + B 1/4 deel 1 + B 1/4 deel 2 + B 1/2 deel 1 + Brons
            $bWedstrijden = 8 + 8 + 4 + 4 + 2 + 2; // = 28
        } elseif ($d >= 16) {
            // D=16: 17-32 judokas
            // B voorronde (overflow) + B 1/8 + B 1/4 deel 1 + B 1/4 deel 2 + B 1/2 deel 1 + Brons
            // B voorronde = max(0, V + 8 - 16) = max(0, N - 16 + 8 - 16) = max(0, N - 24)
            $bVoorronde = max(0, $aantal - 24);
            $bWedstrijden = $bVoorronde + 8 + 4 + 4 + 2 + 2; // = bVoorronde + 20
        } elseif ($d >= 8) {
            // D=8: 9-16 judokas (N = 9-15, want N=16 → D=16)
            // V = N - 8 = 1-7, B 1/4 capaciteit = 8, dus geen overflow
            // B 1/4 deel 1 + B 1/4 deel 2 + B 1/2 deel 1 + Brons
            $bWedstrijden = 4 + 4 + 2 + 2; // = 12
        } else {
            // D=4: 5-8 judokas (simplified)
            // B 1/2 + Brons
            $bWedstrijden = 2 + 2; // = 4
        }

        return $aWedstrijden + $bWedstrijden;
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

        $this->aantal_judokas = $aantalJudokas;
        $this->aantal_wedstrijden = $this->berekenAantalWedstrijden($aantalJudokas);
        $this->save();
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
     * Bereken de gewichtsrange van actieve judoka's in de poule
     * Retourneert [min_kg, max_kg, range] of null als geen gewogen judoka's
     */
    public function getGewichtsRange(): ?array
    {
        $gewichten = $this->judokas()
            ->whereNotNull('gewicht_gewogen')
            ->where(function ($q) {
                $q->whereNull('aanwezigheid')
                  ->orWhere('aanwezigheid', '!=', 'afwezig');
            })
            ->pluck('gewicht_gewogen')
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
     * Check of poule problematisch is na weging (range > max_kg_verschil)
     * Retourneert null als niet problematisch, anders array met details
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
        $maxKgVerschil = $config['max_kg_verschil'] ?? 3;

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
