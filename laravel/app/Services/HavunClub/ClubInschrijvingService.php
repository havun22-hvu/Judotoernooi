<?php

namespace App\Services\HavunClub;

use App\Exceptions\JudoToernooiException;
use App\Models\Judoka;
use App\Models\StamJudoka;
use App\Models\Toernooi;

/**
 * Enters a StamJudoka into a Toernooi on behalf of the HavunClub integration.
 *
 * Deliberately a fresh, API-only service so the existing coach-portal flow
 * (CoachPortalController) keeps working untouched. It enforces the same
 * organisator-facing guards (inschrijving open, max deelnemers, freemium limit)
 * and is idempotent: re-entering the same stam judoka returns the existing row.
 */
class ClubInschrijvingService
{
    public function inschrijf(Toernooi $toernooi, StamJudoka $stam, ?string $naam = null, ?string $band = null, ?float $gewicht = null): Judoka
    {
        if (!$toernooi->isInschrijvingOpen()) {
            throw new JudoToernooiException(
                'HavunClub inschrijving geweigerd: inschrijving gesloten',
                'De inschrijving voor dit toernooi is gesloten.',
                ['toernooi_id' => $toernooi->id],
                4001
            );
        }

        if ($toernooi->isMaxJudokasBereikt()) {
            throw new JudoToernooiException(
                'HavunClub inschrijving geweigerd: max deelnemers bereikt',
                'Maximum aantal deelnemers bereikt.',
                ['toernooi_id' => $toernooi->id],
                4002
            );
        }

        if (!$toernooi->canAddMoreJudokas()) {
            throw new JudoToernooiException(
                'HavunClub inschrijving geweigerd: freemium-limiet bereikt',
                'Maximum aantal judoka\'s voor dit toernooi bereikt. Upgrade is vereist.',
                ['toernooi_id' => $toernooi->id],
                4003
            );
        }

        // Idempotent: same stam judoka already entered into this tournament.
        $bestaande = Judoka::where('toernooi_id', $toernooi->id)
            ->where('stam_judoka_id', $stam->id)
            ->first();
        if ($bestaande) {
            return $bestaande;
        }

        $naam = $naam ?: $stam->naam;
        $band = $band ?: $stam->band;
        $geboortejaar = $stam->geboortejaar;
        $geslacht = $stam->geslacht;
        // HavunClub may send a weigh-in weight at entry; otherwise fall back to
        // the stam judoka's known weight. Drives the gewichtsklasse below.
        $gewicht = $gewicht ?? $stam->gewicht;

        $leeftijdsklasse = null;
        $gewichtsklasse = null;
        if (!empty($geboortejaar) && !empty($geslacht)) {
            $leeftijd = (int) date('Y') - (int) $geboortejaar;
            $leeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $geslacht, $band);
            if (!empty($gewicht)) {
                $gewichtsklasse = $toernooi->bepaalGewichtsklasse((float) $gewicht, $leeftijd, $geslacht, $band);
            }
        }
        if (empty($gewichtsklasse) && !empty($gewicht)) {
            $gewichtsklasse = '-' . (int) $gewicht;
        }

        return Judoka::create([
            'toernooi_id' => $toernooi->id,
            'stam_judoka_id' => $stam->id,
            'naam' => $naam,
            'geboortejaar' => $geboortejaar,
            'geslacht' => $geslacht,
            'band' => $band,
            'gewicht' => $gewicht,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse,
            'synced_at' => now(),
        ]);
    }
}
