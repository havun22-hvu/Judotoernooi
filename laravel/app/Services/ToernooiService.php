<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\DeviceToegang;
use App\Models\Mat;
use App\Models\Toernooi;
use Illuminate\Support\Facades\DB;

class ToernooiService
{
    /**
     * Initialize a new tournament
     */
    public function initialiseerToernooi(array $data): Toernooi
    {
        return DB::transaction(function () use ($data) {
            // Note: Multiple tournaments can now be active simultaneously
            // is_actief is kept for backward compatibility but no longer enforces single-active

            $toernooi = Toernooi::create([
                'naam' => $data['naam'],
                'organisatie' => $data['organisatie'] ?? 'Judoschool Cees Veen',
                'datum' => $data['datum'],
                'locatie' => $data['locatie'] ?? null,
                'verwacht_aantal_judokas' => $data['verwacht_aantal_judokas'] ?? null,
                'aantal_matten' => $data['aantal_matten'] ?? 7,
                'aantal_blokken' => $data['aantal_blokken'] ?? 6,
                'min_judokas_poule' => $data['min_judokas_poule'] ?? 3,
                'optimal_judokas_poule' => $data['optimal_judokas_poule'] ?? 5,
                'max_judokas_poule' => $data['max_judokas_poule'] ?? 6,
                'gewicht_tolerantie' => $data['gewicht_tolerantie'] ?? 0.5,
                'is_actief' => true,
                'gebruik_gewichtsklassen' => false, // Default: dynamische indeling (geen vaste klassen)
            ]);

            // Create blocks
            $this->maakBlokken($toernooi);

            // Create mats
            $this->maakMatten($toernooi);

            // Create default device toegangen
            $this->maakStandaardToegangen($toernooi);

            // Link organisator to tournament as owner
            $organisator = auth('organisator')->user();
            if ($organisator) {
                $organisator->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);
            }

            return $toernooi;
        });
    }

    /**
     * Create time blocks for tournament
     */
    private function maakBlokken(Toernooi $toernooi): void
    {
        for ($i = 1; $i <= $toernooi->aantal_blokken; $i++) {
            Blok::create([
                'toernooi_id' => $toernooi->id,
                'nummer' => $i,
            ]);
        }
    }

    /**
     * Sync blocks to match aantal_blokken setting
     */
    public function syncBlokken(Toernooi $toernooi): void
    {
        $huidigAantal = $toernooi->blokken()->count();
        $gewenstAantal = $toernooi->aantal_blokken ?? 6;

        if ($huidigAantal < $gewenstAantal) {
            // Add missing blocks
            for ($i = $huidigAantal + 1; $i <= $gewenstAantal; $i++) {
                Blok::create([
                    'toernooi_id' => $toernooi->id,
                    'nummer' => $i,
                ]);
            }
        } elseif ($huidigAantal > $gewenstAantal) {
            // Remove excess blocks (only if they have no poules assigned)
            $toernooi->blokken()
                ->where('nummer', '>', $gewenstAantal)
                ->whereDoesntHave('poules')
                ->delete();
        }
    }

    /**
     * Sync mats to match aantal_matten setting
     */
    public function syncMatten(Toernooi $toernooi): void
    {
        $huidigAantal = $toernooi->matten()->count();
        $gewenstAantal = $toernooi->aantal_matten ?? 7;

        if ($huidigAantal < $gewenstAantal) {
            // Add missing mats
            for ($i = $huidigAantal + 1; $i <= $gewenstAantal; $i++) {
                Mat::create([
                    'toernooi_id' => $toernooi->id,
                    'nummer' => $i,
                ]);
            }
        } elseif ($huidigAantal > $gewenstAantal) {
            // Remove excess mats (only if they have no poules assigned)
            $toernooi->matten()
                ->where('nummer', '>', $gewenstAantal)
                ->whereDoesntHave('poules')
                ->delete();
        }
    }

    /**
     * Create mats for tournament
     */
    private function maakMatten(Toernooi $toernooi): void
    {
        for ($i = 1; $i <= $toernooi->aantal_matten; $i++) {
            Mat::create([
                'toernooi_id' => $toernooi->id,
                'nummer' => $i,
            ]);
        }
    }

    /**
     * Create default device toegangen for tournament
     * Creates: 1x Hoofdjury, 1x Mat, 1x Weging, 1x Spreker, 1x Dojo
     */
    private function maakStandaardToegangen(Toernooi $toernooi): void
    {
        $rollen = [
            ['rol' => 'hoofdjury', 'mat_nummer' => null],
            ['rol' => 'mat', 'mat_nummer' => 1],
            ['rol' => 'weging', 'mat_nummer' => null],
            ['rol' => 'spreker', 'mat_nummer' => null],
            ['rol' => 'dojo', 'mat_nummer' => null],
        ];

        foreach ($rollen as $rolData) {
            DeviceToegang::create([
                'toernooi_id' => $toernooi->id,
                'rol' => $rolData['rol'],
                'mat_nummer' => $rolData['mat_nummer'],
            ]);
        }
    }

    /**
     * Get the active tournament
     */
    public function getActiefToernooi(): ?Toernooi
    {
        return Toernooi::actief()->first();
    }

    /**
     * Get tournament statistics
     */
    public function getStatistieken(Toernooi $toernooi): array
    {
        // Use fresh queries to avoid mutation issues
        $betalingen = $toernooi->betalingen()->where('status', 'paid');

        return [
            'totaal_judokas' => $toernooi->judokas()->count(),
            'totaal_poules' => $toernooi->poules()->count(),
            'totaal_wedstrijden' => $toernooi->poules()->sum('aantal_wedstrijden'),
            'aanwezig' => $toernooi->judokas()->where('aanwezigheid', 'aanwezig')->count(),
            'afwezig' => $toernooi->judokas()->where('aanwezigheid', 'afwezig')->count(),
            'onbekend' => $toernooi->judokas()->where('aanwezigheid', 'onbekend')->count(),
            'gewogen' => $toernooi->judokas()->whereNotNull('gewicht_gewogen')->count(),
            'per_leeftijdsklasse' => $this->getStatistiekenPerLeeftijdsklasse($toernooi),
            'per_blok' => $this->getStatistiekenPerBlok($toernooi),
            // Payment stats
            'betaald_judokas' => $toernooi->judokas()->whereNotNull('betaald_op')->count(),
            'totaal_ontvangen' => $betalingen->sum('bedrag'),
            'aantal_betalingen' => $betalingen->count(),
        ];
    }

    private function getStatistiekenPerLeeftijdsklasse(Toernooi $toernooi): array
    {
        // Sort by sort_categorie (young to old) - respects preset order
        return $toernooi->judokas()
            ->selectRaw('leeftijdsklasse, MIN(sort_categorie) as sort_order, COUNT(*) as aantal')
            ->groupBy('leeftijdsklasse')
            ->orderBy('sort_order')
            ->pluck('aantal', 'leeftijdsklasse')
            ->toArray();
    }

    private function getStatistiekenPerBlok(Toernooi $toernooi): array
    {
        return $toernooi->blokken()
            ->withCount('poules')
            ->withSum('poules', 'aantal_wedstrijden')
            ->get()
            ->mapWithKeys(fn($blok) => [
                $blok->nummer => [
                    'poules' => $blok->poules_count,
                    'wedstrijden' => $blok->poules_sum_aantal_wedstrijden ?? 0,
                    'weging_gesloten' => $blok->weging_gesloten,
                ]
            ])
            ->toArray();
    }
}
