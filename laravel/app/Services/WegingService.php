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
