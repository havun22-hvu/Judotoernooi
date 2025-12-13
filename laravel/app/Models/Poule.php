<?php

namespace App\Models;

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
        'aantal_judokas',
        'aantal_wedstrijden',
        'spreker_klaar',
    ];

    protected $casts = [
        'spreker_klaar' => 'datetime',
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
     * Formula: n*(n-1)/2 for round-robin, doubled if dubbel spel enabled
     */
    public function berekenAantalWedstrijden(?int $aantalJudokas = null): int
    {
        $aantal = $aantalJudokas ?? $this->aantal_judokas;
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
     * Optimized to give each judoka rest between their matches
     */
    private function getOptimaleWedstrijdvolgorde(int $aantal): array
    {
        // Predefined optimal orders for common pool sizes
        $schema = match ($aantal) {
            2 => [
                [1, 2],
                [1, 2],  // Double round
            ],
            3 => [
                // Double round for 3: each pair plays twice
                [1, 2], [1, 3], [2, 3],
                [1, 2], [1, 3], [2, 3],
            ],
            4 => [
                // Optimal: each judoka rests 1 match between games
                [1, 2], [3, 4],  // 1,2 play; 3,4 play
                [2, 3], [1, 4],  // 2,3 play; 1,4 play
                [2, 4], [1, 3],  // 2,4 play; 1,3 play
            ],
            5 => [
                // Optimal order with rest between matches
                [1, 2], [3, 4], [1, 5], [2, 3], [4, 5],
                [1, 3], [2, 4], [3, 5], [1, 4], [2, 5],
            ],
            6 => [
                // 15 matches with optimal rest
                [1, 2], [3, 4], [5, 6],
                [1, 3], [2, 5], [4, 6],
                [3, 5], [2, 4], [1, 6],
                [2, 3], [4, 5], [3, 6],
                [1, 4], [2, 6], [1, 5],
            ],
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
