<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Wedstrijd;
use Illuminate\Http\JsonResponse;

/**
 * Shared helper for optimistic locking on Wedstrijd updates.
 *
 * Used by MatController and its split companions (MatUitslagController,
 * MatBracketController) so every mat-side write can detect stale client state.
 */
trait HandlesWedstrijdConflict
{
    /**
     * Check for optimistic locking conflict.
     * Returns a conflict JsonResponse if the wedstrijd was modified since the client loaded it.
     */
    protected function checkConflict(Wedstrijd $wedstrijd, ?string $clientUpdatedAt): ?JsonResponse
    {
        if (!$clientUpdatedAt || !$wedstrijd->updated_at) {
            return null;
        }

        $clientTime = \Carbon\Carbon::parse($clientUpdatedAt);
        // Allow 1 second tolerance for clock drift / serialization differences
        if ($wedstrijd->updated_at->gt($clientTime->copy()->addSecond())) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => 'Deze wedstrijd is zojuist gewijzigd door een ander apparaat. De pagina wordt herladen.',
                'server_updated_at' => $wedstrijd->updated_at->toISOString(),
            ], 409);
        }

        return null;
    }
}
