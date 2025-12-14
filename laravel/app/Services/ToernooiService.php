<?php

namespace App\Services;

use App\Models\Blok;
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
            // Deactivate any existing active tournaments
            Toernooi::where('is_actief', true)->update(['is_actief' => false]);

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
            ]);

            // Create blocks
            $this->maakBlokken($toernooi);

            // Create mats
            $this->maakMatten($toernooi);

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
        $judokas = $toernooi->judokas();
        $poules = $toernooi->poules();

        return [
            'totaal_judokas' => $judokas->count(),
            'totaal_poules' => $poules->count(),
            'totaal_wedstrijden' => $poules->sum('aantal_wedstrijden'),
            'aanwezig' => $judokas->where('aanwezigheid', 'aanwezig')->count(),
            'afwezig' => $judokas->where('aanwezigheid', 'afwezig')->count(),
            'onbekend' => $judokas->where('aanwezigheid', 'onbekend')->count(),
            'gewogen' => $judokas->whereNotNull('gewicht_gewogen')->count(),
            'per_leeftijdsklasse' => $this->getStatistiekenPerLeeftijdsklasse($toernooi),
            'per_blok' => $this->getStatistiekenPerBlok($toernooi),
        ];
    }

    private function getStatistiekenPerLeeftijdsklasse(Toernooi $toernooi): array
    {
        return $toernooi->judokas()
            ->selectRaw('leeftijdsklasse, COUNT(*) as aantal')
            ->groupBy('leeftijdsklasse')
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

    /**
     * Close weighing for a block
     */
    public function sluitWegingBlok(Blok $blok): void
    {
        $blok->sluitWeging();
    }
}
