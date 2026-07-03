<?php

namespace App\Services\HavunClub;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Toernooi;

/**
 * Enters a judoka into an invited school portal on behalf of HavunClub
 * (scenario 2). Club-scoped: the resulting Judoka carries a club_id (like the
 * web coach portal) rather than a stam_judoka_id, because a school invited to
 * another organiser's tournament has no stam roster on that Organisator.
 *
 * Deliberately API-only, so the session/redirect-based CoachPortalController
 * flow keeps working untouched. Idempotent: a repeated push of the same judoka
 * (matched on the HavunClub id, else on naam+geboortejaar) returns the existing
 * row instead of creating a duplicate.
 */
class SchoolPortalInschrijvingService
{
    /**
     * @param  array<string,mixed>  $data  Validated HavunClub payload.
     */
    public function vulPortal(Toernooi $toernooi, Club $club, array $data): Judoka
    {
        $ref = isset($data['havunclub_judoka_id']) && $data['havunclub_judoka_id'] !== null
            ? (string) $data['havunclub_judoka_id']
            : null;

        $naam = trim(($data['voornaam'] ?? '') . ' ' . ($data['achternaam'] ?? ''));
        $geboortejaar = !empty($data['geboortedatum'])
            ? (int) date('Y', strtotime((string) $data['geboortedatum']))
            : null;
        $geslacht = !empty($data['geslacht']) ? $this->normalizeGeslacht((string) $data['geslacht']) : null;
        $band = $data['band'] ?? null;
        $gewicht = isset($data['gewicht']) && $data['gewicht'] !== null ? (float) $data['gewicht'] : null;

        // Idempotency: prefer the deterministic HavunClub id, else fall back to
        // the web portal's naam+geboortejaar dedup within this club+tournament.
        $bestaande = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->when($ref !== null, fn ($q) => $q->where('havunclub_ref', $ref))
            ->when($ref === null, fn ($q) => $q->where('naam', $naam)->where('geboortejaar', $geboortejaar))
            ->first();
        if ($bestaande) {
            return $bestaande;
        }

        $leeftijdsklasse = null;
        $gewichtsklasse = null;
        if (!empty($geboortejaar) && !empty($geslacht)) {
            $leeftijd = (int) date('Y') - $geboortejaar;
            $leeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $geslacht, $band);
            if (!empty($gewicht)) {
                $gewichtsklasse = $toernooi->bepaalGewichtsklasse($gewicht, $leeftijd, $geslacht, $band);
            }
        }
        if (empty($gewichtsklasse) && !empty($gewicht)) {
            $gewichtsklasse = '-' . (int) $gewicht;
        }

        return Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'havunclub_ref' => $ref,
            'naam' => $naam,
            'geboortejaar' => $geboortejaar,
            'geslacht' => $geslacht,
            'band' => $band,
            'gewicht' => $gewicht,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse,
        ]);
    }

    private function normalizeGeslacht(string $value): string
    {
        return match (mb_strtolower(trim($value))) {
            'm', 'man', 'male', 'jongen' => 'M',
            'v', 'f', 'vrouw', 'female', 'meisje' => 'V',
            default => mb_strtoupper(mb_substr($value, 0, 1)),
        };
    }
}
