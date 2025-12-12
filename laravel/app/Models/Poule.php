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

    public function updateStatistieken(): void
    {
        // Refresh the judokas relation to get fresh data after detach/attach
        $this->load('judokas');

        // Count only active judokas: not absent AND within weight class (if weighed)
        $activeJudokas = $this->judokas
            ->where('aanwezigheid', '!=', 'afwezig')
            ->filter(fn($j) => $j->isGewichtBinnenKlasse())
            ->count();

        $this->aantal_judokas = $activeJudokas;
        $this->aantal_wedstrijden = $this->berekenAantalWedstrijden($activeJudokas);
        $this->save();
    }

    /**
     * Generate match schedule for this poule
     * Returns optimal match order to minimize consecutive matches for same judoka
     */
    public function genereerWedstrijdSchema(): array
    {
        $judokas = $this->judokas->pluck('id')->toArray();
        $aantal = count($judokas);

        if ($aantal < 2) {
            return [];
        }

        // Standard round-robin pairing
        $wedstrijden = [];

        if ($aantal === 3) {
            // Double round for 3 judokas: each pair plays twice
            // Order: 1-2, 1-3, 2-3, 1-2, 1-3, 2-3
            $wedstrijden = [
                [$judokas[0], $judokas[1]],
                [$judokas[0], $judokas[2]],
                [$judokas[1], $judokas[2]],
                [$judokas[0], $judokas[1]],
                [$judokas[0], $judokas[2]],
                [$judokas[1], $judokas[2]],
            ];
        } elseif ($aantal === 4) {
            // Optimal order for 4: 1-2, 3-4, 1-3, 2-4, 1-4, 2-3
            $wedstrijden = [
                [$judokas[0], $judokas[1]],
                [$judokas[2], $judokas[3]],
                [$judokas[0], $judokas[2]],
                [$judokas[1], $judokas[3]],
                [$judokas[0], $judokas[3]],
                [$judokas[1], $judokas[2]],
            ];
        } else {
            // Standard round-robin for 5+ judokas
            for ($i = 0; $i < $aantal; $i++) {
                for ($j = $i + 1; $j < $aantal; $j++) {
                    $wedstrijden[] = [$judokas[$i], $judokas[$j]];
                }
            }
        }

        return $wedstrijden;
    }
}
