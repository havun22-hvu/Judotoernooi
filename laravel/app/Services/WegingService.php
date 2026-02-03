<?php

namespace App\Services;

use App\Enums\AanwezigheidsStatus;
use App\Models\Judoka;
use App\Models\Toernooi;
use App\Models\Weging;
use Illuminate\Support\Facades\DB;

class WegingService
{
    /**
     * Register weight for judoka
     * Note: max_wegingen is only a warning in the UI, not a hard limit
     */
    public function registreerGewicht(Judoka $judoka, float $gewicht, ?string $geregistreerdDoor = null): array
    {
        return DB::transaction(function () use ($judoka, $gewicht, $geregistreerdDoor) {
            $tolerantie = $judoka->toernooi->gewicht_tolerantie ?? 0.5;
            $binnenKlasse = $judoka->isGewichtBinnenKlasse($gewicht, $tolerantie);

            $alternatievePoule = null;
            $opmerking = null;

            if (!$binnenKlasse) {
                $result = $this->bepaalAlternatief($judoka, $gewicht);
                $alternatievePoule = $result['alternatief'];
                $opmerking = $result['opmerking'];
            }

            // Create weging record
            $weging = Weging::create([
                'judoka_id' => $judoka->id,
                'gewicht' => $gewicht,
                'binnen_klasse' => $binnenKlasse,
                'alternatieve_poule' => $alternatievePoule,
                'opmerking' => $opmerking,
                'geregistreerd_door' => $geregistreerdDoor,
            ]);

            // Update judoka
            $judoka->update([
                'gewicht_gewogen' => $gewicht,
                'aanwezigheid' => AanwezigheidsStatus::AANWEZIG->value,
                'opmerking' => $opmerking,
            ]);

            // Bij vaste gewichtsklassen en overpoulen: verplaats naar wachtruimte
            // Check per CATEGORIE, niet per toernooi (toernooi kan mix hebben)
            if (!$binnenKlasse && $this->heeftVasteGewichtsklassenVoorJudoka($judoka)) {
                $this->verplaatsOverpoulerNaarWachtruimte($judoka);
            }

            // Verwijder uit poules als afwezig
            $judoka->verwijderUitPoulesIndienNodig();

            return [
                'success' => true,
                'weging' => $weging,
                'binnen_klasse' => $binnenKlasse,
                'alternatieve_poule' => $alternatievePoule,
                'opmerking' => $opmerking,
            ];
        });
    }

    /**
     * Check of de CATEGORIE van deze judoka vaste gewichtsklassen gebruikt
     * (niet per toernooi, want toernooi kan mix van vast + variabel hebben)
     */
    private function heeftVasteGewichtsklassenVoorJudoka(Judoka $judoka): bool
    {
        $toernooi = $judoka->toernooi;

        // Guard: toernooi moet bestaan
        if (!$toernooi) {
            return true; // Default: vaste klassen (veiligste optie)
        }

        $categorieKey = $judoka->categorie_key;

        // Probeer categorie config op te halen
        $config = $toernooi->getAlleGewichtsklassen();

        // Als we de categorie_key hebben, check die specifieke categorie
        if ($categorieKey && isset($config[$categorieKey])) {
            $maxKgVerschil = $config[$categorieKey]['max_kg_verschil'] ?? 0;
            return $maxKgVerschil == 0;
        }

        // Fallback: zoek op leeftijdsklasse label
        foreach ($config as $key => $cat) {
            if (($cat['label'] ?? $key) === $judoka->leeftijdsklasse) {
                $maxKgVerschil = $cat['max_kg_verschil'] ?? 0;
                return $maxKgVerschil == 0;
            }
        }

        // Default: neem aan dat het vaste klassen zijn (veiligste optie)
        return true;
    }

    /**
     * Verplaats overpouler naar wachtruimte (bij vaste gewichtsklassen)
     * - Onthoud de oude poule
     * - Verwijder uit poule
     * - Update gewichtsklasse naar de juiste nieuwe klasse
     */
    private function verplaatsOverpoulerNaarWachtruimte(Judoka $judoka): void
    {
        // Vind de voorronde poule waar de judoka in zit
        $oudePoule = $judoka->poules()->where('type', 'voorronde')->first();

        if (!$oudePoule) {
            return;
        }

        // Sla de oude poule op voor de (i) popup
        $judoka->update([
            'overpouled_van_poule_id' => $oudePoule->id,
        ]);

        // Verwijder uit de poule (gaat naar wachtruimte)
        $oudePoule->judokas()->detach($judoka->id);
        $oudePoule->updateStatistieken();

        // Bepaal nieuwe gewichtsklasse op basis van gewogen gewicht
        $nieuweKlasse = $this->bepaalNieuweGewichtsklasse($judoka);
        if ($nieuweKlasse) {
            $judoka->update(['gewichtsklasse' => $nieuweKlasse]);
        }
    }

    /**
     * Bepaal nieuwe gewichtsklasse op basis van gewogen gewicht
     * Gebruikt de werkelijke gewichtsklassen uit de leeftijdscategorie
     */
    private function bepaalNieuweGewichtsklasse(Judoka $judoka): ?string
    {
        $gewicht = $judoka->gewicht_gewogen;
        if (!$gewicht) return null;

        $tolerantie = $judoka->toernooi->gewicht_tolerantie ?? 0.5;

        // Haal beschikbare gewichtsklassen op voor deze leeftijdscategorie
        $klassen = Judoka::where('leeftijdsklasse', $judoka->leeftijdsklasse)
            ->where('toernooi_id', $judoka->toernooi_id)
            ->whereNotNull('gewichtsklasse')
            ->distinct()
            ->pluck('gewichtsklasse')
            ->toArray();

        // Sorteer klassen van licht naar zwaar
        usort($klassen, function ($a, $b) {
            $aNum = floatval(preg_replace('/[^0-9.]/', '', $a));
            $bNum = floatval(preg_replace('/[^0-9.]/', '', $b));
            $aPlus = str_starts_with($a, '+');
            $bPlus = str_starts_with($b, '+');

            if ($aPlus && !$bPlus) return 1;
            if (!$aPlus && $bPlus) return -1;
            return $aNum - $bNum;
        });

        // Vind de laagste klasse waar het gewicht in past (met tolerantie)
        foreach ($klassen as $klasse) {
            $isPlusKlasse = str_starts_with($klasse, '+');
            $limiet = floatval(preg_replace('/[^0-9.]/', '', $klasse));

            if ($isPlusKlasse) {
                // +78 = boven 78kg (dit is altijd de laatste optie)
                return $klasse;
            } else {
                // -30 = max 30kg (+ tolerantie)
                if ($gewicht <= $limiet + $tolerantie) {
                    return $klasse;
                }
            }
        }

        // Fallback: hoogste klasse (+ klasse als die bestaat)
        return end($klassen) ?: null;
    }

    /**
     * Determine alternative pool suggestion when weight is out of range
     */
    private function bepaalAlternatief(Judoka $judoka, float $gewicht): array
    {
        $gewichtsklasse = $judoka->gewichtsklasse;
        $isPlusKlasse = str_starts_with($gewichtsklasse, '+');
        $limiet = floatval(preg_replace('/[^0-9.]/', '', $gewichtsklasse));

        $opmerking = '';
        $alternatief = null;

        if ($isPlusKlasse) {
            // +70 category, but judoka is lighter
            if ($gewicht < $limiet) {
                $opmerking = "Te licht! Minimaal {$limiet}kg.";
                $alternatief = "-{$limiet}kg";
            }
        } else {
            // -36 category, but judoka is heavier
            if ($gewicht > $limiet) {
                // Calculate next weight class (usually +4kg steps)
                $volgendeKlasse = $limiet + 4;
                $opmerking = "Te zwaar! Maximaal {$limiet}kg.";
                $alternatief = "-{$volgendeKlasse}kg";
            }
        }

        return [
            'alternatief' => $alternatief,
            'opmerking' => $opmerking,
        ];
    }

    /**
     * Get weighing list for a tournament
     */
    public function getWeeglijst(Toernooi $toernooi, ?int $blokNummer = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $toernooi->judokas()
            ->with(['club', 'poules.blok', 'poules.mat', 'wegingen' => fn($q) => $q->latest()])
            ->orderBy('naam');

        if ($blokNummer) {
            $query->whereHas('poules.blok', fn($q) => $q->where('nummer', $blokNummer));
        }

        return $query->get();
    }

    /**
     * Mark judoka as present
     */
    public function markeerAanwezig(Judoka $judoka): void
    {
        $judoka->update([
            'aanwezigheid' => AanwezigheidsStatus::AANWEZIG->value,
        ]);

        // Update poule statistics
        foreach ($judoka->poules as $poule) {
            $poule->updateStatistieken();
        }
    }

    /**
     * Mark judoka as absent (but keep in poules for traceability)
     *
     * On tournament day, absent judokas should:
     * - Stay in poules (shown as strikethrough for traceability)
     * - Stay in judoka list (with absent status)
     * - Stay in weging list (with absent status)
     * - NOT appear in wedstrijddag matches
     */
    public function markeerAfwezig(Judoka $judoka): void
    {
        $judoka->update([
            'aanwezigheid' => AanwezigheidsStatus::AFWEZIG->value,
        ]);

        // DO NOT remove from poules - keep for traceability
        // Views should show afwezig judokas as strikethrough
    }

    /**
     * Find judoka by QR code
     * Accepts both raw qr_code and full URL (e.g., /weegkaart/ABC123)
     */
    public function vindJudokaViaQR(string $qrCode): ?Judoka
    {
        $original = $qrCode;

        // Extract qr_code from URL if full URL is provided
        if (str_contains($qrCode, '/weegkaart/')) {
            $parts = explode('/weegkaart/', $qrCode);
            $qrCode = end($parts);
            // Remove any trailing slashes, query params, or hash
            $qrCode = strtok($qrCode, '?');
            $qrCode = strtok($qrCode, '#');
            $qrCode = rtrim($qrCode, '/');
        }

        \Log::info('QR Scan', ['original' => $original, 'extracted' => $qrCode]);

        $judoka = Judoka::where('qr_code', $qrCode)->first();

        if (!$judoka) {
            \Log::warning('QR not found', ['qr_code' => $qrCode]);
        }

        return $judoka;
    }

    /**
     * Search judokas by name
     */
    public function zoekJudokaOpNaam(Toernooi $toernooi, string $zoekterm, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $toernooi->judokas()
            ->where('naam', 'LIKE', "%{$zoekterm}%")
            ->with(['club', 'poules'])
            ->orderBy('naam')
            ->limit($limit)
            ->get();
    }
}
