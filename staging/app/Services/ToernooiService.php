<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\DeviceToegang;
use App\Models\Mat;
use App\Models\Toernooi;
use App\Models\ToernooiTemplate;
use Illuminate\Support\Facades\DB;

class ToernooiService
{
    /**
     * Initialize a new tournament
     * Automatically cleans up old tournaments from the same organisator
     */
    public function initialiseerToernooi(array $data): Toernooi
    {
        return DB::transaction(function () use ($data) {
            // Get the owner organisator
            $organisator = auth('organisator')->user();

            // Clean up old tournaments from this organisator (fresh start)
            if ($organisator) {
                $this->verwijderOudeToernooien($organisator->id);
            }

            $toernooi = Toernooi::create([
                'organisator_id' => $organisator?->id,
                'naam' => $data['naam'],
                'organisatie' => $data['organisatie'] ?? $organisator?->naam ?? 'Judoschool Cees Veen',
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

            // Apply template if provided
            if (!empty($data['template_id'])) {
                $template = ToernooiTemplate::find($data['template_id']);
                if ($template) {
                    $template->applyToToernooi($toernooi);
                }
            } else {
                // No template: add default category for dynamic tournaments
                $toernooi->update([
                    'gewichtsklassen' => [
                        'standaard' => [
                            'label' => 'Standaard',
                            'max_leeftijd' => 99,
                            'geslacht' => 'gemengd',
                            'max_kg_verschil' => 3,
                            'max_leeftijd_verschil' => 1,
                            'max_band_verschil' => 2,
                            'band_streng_beginners' => true,
                            'gewichten' => [],
                        ],
                    ],
                ]);
            }

            // Create blocks
            $this->maakBlokken($toernooi);

            // Create mats
            $this->maakMatten($toernooi);

            // Create default device toegangen
            $this->maakStandaardToegangen($toernooi);

            // Link organisator to tournament as owner (pivot for access control)
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
     * Returns array with info about moved poules (for warning message)
     */
    public function syncBlokken(Toernooi $toernooi): array
    {
        $huidigAantal = $toernooi->blokken()->count();
        $gewenstAantal = $toernooi->aantal_blokken ?? 6;
        $verplaatstePoules = 0;

        if ($huidigAantal < $gewenstAantal) {
            // Add missing blocks
            for ($i = $huidigAantal + 1; $i <= $gewenstAantal; $i++) {
                Blok::create([
                    'toernooi_id' => $toernooi->id,
                    'nummer' => $i,
                ]);
            }
        } elseif ($huidigAantal > $gewenstAantal) {
            // Get blocks to remove
            $blokkenTeVerwijderen = $toernooi->blokken()
                ->where('nummer', '>', $gewenstAantal)
                ->get();

            foreach ($blokkenTeVerwijderen as $blok) {
                // Move poules from this block to sleepvak (blok_id = null)
                $aantalPoules = $blok->poules()->count();
                if ($aantalPoules > 0) {
                    $blok->poules()->update(['blok_id' => null]);
                    $verplaatstePoules += $aantalPoules;
                }

                // Now safe to delete the block
                $blok->delete();
            }
        }

        return [
            'verplaatste_poules' => $verplaatstePoules,
        ];
    }

    /**
     * Kindvriendelijke heldere kleuren voor matten
     */
    private const MAT_KLEUREN = [
        1 => 'rood',
        2 => 'blauw',
        3 => 'groen',
        4 => 'geel',
        5 => 'oranje',
        6 => 'paars',
        7 => 'roze',
        8 => 'bruin',
        9 => 'wit',
        10 => 'zwart',
    ];

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
                    'kleur' => self::MAT_KLEUREN[$i] ?? null,
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
                'kleur' => self::MAT_KLEUREN[$i] ?? null,
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
        $counts = $toernooi->judokas()
            ->selectRaw('leeftijdsklasse, COUNT(*) as aantal')
            ->groupBy('leeftijdsklasse')
            ->pluck('aantal', 'leeftijdsklasse')
            ->toArray();

        // Sort by max_leeftijd from config (youngest first)
        uksort($counts, fn($a, $b) =>
            $toernooi->getLeeftijdsklasseSortValue($a) <=> $toernooi->getLeeftijdsklasseSortValue($b)
        );

        return $counts;
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
     * Verwijder alle oude toernooien van een organisator
     * Wordt aangeroepen bij aanmaken nieuw toernooi voor een frisse start
     */
    public function verwijderOudeToernooien(int $organisatorId): int
    {
        $oudeToernooien = Toernooi::where('organisator_id', $organisatorId)->get();
        $verwijderd = 0;

        foreach ($oudeToernooien as $toernooi) {
            $this->verwijderToernooi($toernooi);
            $verwijderd++;
        }

        return $verwijderd;
    }

    /**
     * Verwijder een toernooi en alle gerelateerde data
     */
    public function verwijderToernooi(Toernooi $toernooi): void
    {
        DB::transaction(function () use ($toernooi) {
            // Verwijder in volgorde van afhankelijkheden
            // 1. Wedstrijden (hangen aan poules)
            DB::table('wedstrijden')
                ->whereIn('poule_id', $toernooi->poules()->pluck('id'))
                ->delete();

            // 2. Poule-judoka koppelingen
            DB::table('poule_judoka')
                ->whereIn('poule_id', $toernooi->poules()->pluck('id'))
                ->delete();

            // 3. Poules
            $toernooi->poules()->delete();

            // 4. Blokken
            $toernooi->blokken()->delete();

            // 5. Matten
            $toernooi->matten()->delete();

            // 6. Judokas
            $toernooi->judokas()->delete();

            // 7. Device toegangen
            $toernooi->deviceToegangen()->delete();

            // 8. Organisator-toernooi koppeling (pivot)
            DB::table('organisator_toernooi')
                ->where('toernooi_id', $toernooi->id)
                ->delete();

            // 9. Het toernooi zelf
            $toernooi->delete();
        });
    }
}
