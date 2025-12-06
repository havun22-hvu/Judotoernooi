<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Toernooi;
use App\Services\ToernooiService;
use Illuminate\Http\JsonResponse;

class ToernooiApiController extends Controller
{
    public function __construct(
        private ToernooiService $toernooiService
    ) {}

    public function actief(): JsonResponse
    {
        $toernooi = $this->toernooiService->getActiefToernooi();

        if (!$toernooi) {
            return response()->json([
                'success' => false,
                'message' => 'Geen actief toernooi',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'toernooi' => [
                'id' => $toernooi->id,
                'naam' => $toernooi->naam,
                'datum' => $toernooi->datum->format('Y-m-d'),
                'organisatie' => $toernooi->organisatie,
            ],
        ]);
    }

    public function statistieken(Toernooi $toernooi): JsonResponse
    {
        $statistieken = $this->toernooiService->getStatistieken($toernooi);

        return response()->json([
            'success' => true,
            'statistieken' => $statistieken,
        ]);
    }

    public function blokken(Toernooi $toernooi): JsonResponse
    {
        $blokken = $toernooi->blokken->map(fn($b) => [
            'id' => $b->id,
            'nummer' => $b->nummer,
            'naam' => $b->naam,
            'weging_gesloten' => $b->weging_gesloten,
        ]);

        return response()->json([
            'success' => true,
            'blokken' => $blokken,
        ]);
    }

    public function matten(Toernooi $toernooi): JsonResponse
    {
        $matten = $toernooi->matten->map(fn($m) => [
            'id' => $m->id,
            'nummer' => $m->nummer,
            'naam' => $m->label,
        ]);

        return response()->json([
            'success' => true,
            'matten' => $matten,
        ]);
    }
}
