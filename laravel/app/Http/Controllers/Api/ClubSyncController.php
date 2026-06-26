<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InschrijvingRequest;
use App\Http\Requests\Api\SyncJudokaRequest;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Services\HavunClub\ClubInschrijvingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HavunClub integration API.
 *
 * HavunClub is the caller; JudoToernooi stores stamdata, registers entries and
 * serves results. The tenant (Organisator) comes from the club.token middleware,
 * so there is no tenant parameter. Everything here is additive — solo
 * JudoToernooi never calls these endpoints.
 *
 * Contract: HavunCore/docs/kb/contracts/havunclub-koppelingen.md
 */
class ClubSyncController extends Controller
{
    /**
     * POST /api/judokas — idempotent upsert of a stam judoka.
     */
    public function upsertJudoka(SyncJudokaRequest $request): JsonResponse
    {
        $org = $this->organisator($request);
        $ref = $request->input('havunclub_judoka_id');

        $stam = null;
        if ($request->filled('judotoernooi_id')) {
            $stam = StamJudoka::where('organisator_id', $org->id)
                ->find($request->integer('judotoernooi_id'));
        }
        if (!$stam && $ref) {
            $stam = StamJudoka::where('organisator_id', $org->id)
                ->where('havunclub_ref', $ref)
                ->first();
        }

        $data = [
            'organisator_id' => $org->id,
            'naam' => trim($request->input('voornaam') . ' ' . $request->input('achternaam')),
            'geboortejaar' => (int) date('Y', strtotime($request->input('geboortedatum'))),
            'geslacht' => $this->normalizeGeslacht($request->input('geslacht')),
            'band' => $request->input('band'),
            'actief' => true,
        ];
        if ($ref) {
            $data['havunclub_ref'] = $ref;
        }

        if ($stam) {
            $stam->update($data);
        } else {
            $stam = StamJudoka::create($data);
        }

        return response()->json(['id' => $stam->id]);
    }

    /**
     * POST /api/inschrijvingen — enter a stam judoka into a tournament.
     */
    public function inschrijven(InschrijvingRequest $request, ClubInschrijvingService $service): JsonResponse
    {
        $org = $this->organisator($request);

        // findOrFail → 404 JSON when the resource is not in this tenant: isolation.
        $toernooi = Toernooi::where('organisator_id', $org->id)
            ->findOrFail($request->integer('toernooi_id'));
        $stam = StamJudoka::where('organisator_id', $org->id)
            ->findOrFail($request->integer('judoka_id'));

        $judoka = $service->inschrijf(
            $toernooi,
            $stam,
            $request->input('naam'),
            $request->input('band'),
        );

        return response()->json(['id' => $judoka->id]);
    }

    /**
     * GET /api/toernooien/{toernooi}/resultaten — placements per judoka.
     *
     * `resultaat` is the eindpositie (1 = goud, 2 = zilver, 3 = brons, ...).
     */
    public function resultaten(Request $request, int $toernooi): JsonResponse
    {
        $org = $this->organisator($request);

        $toernooiModel = Toernooi::where('organisator_id', $org->id)
            ->findOrFail($toernooi);
        $toernooiModel->load('poules.judokas');

        $results = [];
        foreach ($toernooiModel->poules as $poule) {
            foreach ($poule->judokas as $judoka) {
                $pivot = $judoka->pivot;
                if ($pivot->eindpositie === null) {
                    continue;
                }
                $results[] = [
                    'judoka_id' => $judoka->id,
                    'stam_judoka_id' => $judoka->stam_judoka_id,
                    'naam' => $judoka->naam,
                    'gewichtsklasse' => $poule->gewichtsklasse,
                    'resultaat' => (int) $pivot->eindpositie,
                    'partijen' => (int) $pivot->gewonnen + (int) $pivot->verloren + (int) $pivot->gelijk,
                ];
            }
        }

        return response()->json($results);
    }

    private function organisator(Request $request): Organisator
    {
        return $request->attributes->get('club_organisator');
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
