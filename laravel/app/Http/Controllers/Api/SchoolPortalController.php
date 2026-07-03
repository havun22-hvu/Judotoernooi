<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SchoolPortalInschrijvingRequest;
use App\Models\Club;
use App\Models\Toernooi;
use App\Services\HavunClub\SchoolPortalInschrijvingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * HavunClub school-portal fill API (integration scenario 2).
 *
 * A judoschool invited to *another* organiser's tournament fills its portal
 * from HavunClub, authorised by the per-tournament portal code + 5-digit PIN
 * the organiser sent — NOT the global ClubApiToken (which is scoped to a whole
 * Organisator). This mirrors the session-based CoachPortalController as a
 * stateless JSON endpoint and reuses the same PIN brute-force guard.
 *
 * Contract: HavunCore/docs/kb/contracts/havunclub-koppelingen.md
 */
class SchoolPortalController extends Controller
{
    public function inschrijven(
        SchoolPortalInschrijvingRequest $request,
        string $code,
        SchoolPortalInschrijvingService $service
    ): JsonResponse {
        // The portal code (from the invitation link) locates the club + tournament.
        // It is per-tournament unique and generated as a 12-char random string,
        // so a single lookup is unambiguous in practice.
        $pivot = DB::table('club_toernooi')->where('portal_code', $code)->first();
        if (!$pivot) {
            return response()->json(['message' => 'Onbekende portal-code'], 404);
        }

        $toernooi = Toernooi::findOrFail($pivot->toernooi_id);
        $club = Club::findOrFail($pivot->club_id);

        // Brute-force guard on the readable 5-digit PIN, mirroring the web portal
        // (5 attempts, 300s decay) on top of the route's IP throttle.
        $throttleKey = "school-portal-api-pin:{$toernooi->id}:{$code}";
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => 'Te veel pogingen. Probeer later opnieuw.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }
        if (!$club->checkPincodeForToernooi($toernooi, (string) $request->input('pincode'))) {
            RateLimiter::hit($throttleKey, 300);

            return response()->json(['message' => 'Onjuiste PIN code'], 401);
        }
        RateLimiter::clear($throttleKey);

        // Same entry guards as the organiser-facing coach portal.
        if (!$toernooi->portaalMagInschrijven() || !$toernooi->isInschrijvingOpen()) {
            return response()->json(['message' => 'De inschrijving is gesloten.'], 422);
        }
        if ($toernooi->isMaxJudokasBereikt() || !$toernooi->canAddMoreJudokas()) {
            return response()->json(['message' => 'Maximum aantal deelnemers bereikt.'], 422);
        }

        $judoka = $service->vulPortal($toernooi, $club, $request->validated());

        return response()->json(['id' => $judoka->id]);
    }
}
