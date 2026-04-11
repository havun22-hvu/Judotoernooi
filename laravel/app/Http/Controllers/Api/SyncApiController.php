<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\SyncConflict;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncApiController extends Controller
{
    /**
     * Export full tournament data for local sync
     */
    public function export(Toernooi $toernooi): JsonResponse
    {
        $data = [
            'toernooi' => $toernooi->toArray(),
            'clubs' => $this->exportClubs($toernooi),
            'blokken' => $this->exportBlokken($toernooi),
            'matten' => $this->exportMatten($toernooi),
            'judokas' => $this->exportJudokas($toernooi),
            'poules' => $this->exportPoules($toernooi),
            'wedstrijden' => $this->exportWedstrijden($toernooi),
            'exported_at' => now()->toIso8601String(),
        ];

        return response()->json($data);
    }

    /**
     * Receive changes from local server
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'toernooi_id' => 'required|exists:toernooien,id',
            'last_synced_at' => 'sometimes|nullable|date',
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.table' => 'required|string|in:wedstrijden,judokas',
            'items.*.record_id' => 'required|integer',
            'items.*.action' => 'required|string|in:create,update,delete',
            'items.*.payload' => 'required|array',
        ]);

        $synced = [];
        $errors = [];
        $conflicts = [];
        $lastSyncedAt = $validated['last_synced_at'] ?? null;

        DB::beginTransaction();

        try {
            foreach ($validated['items'] as $item) {
                try {
                    $result = $this->processItem($item, $lastSyncedAt);
                    $synced[] = $item['id'];
                    if ($result === 'conflict') {
                        $conflicts[] = $item['id'];
                    }
                } catch (\Exception $e) {
                    $errors[$item['id']] = $e->getMessage();
                    Log::error("Sync item failed", [
                        'item_id' => $item['id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Transaction failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'synced' => $synced,
            'errors' => $errors,
            'conflicts' => $conflicts,
            'received_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Process a single sync item.
     *
     * Returns 'conflict' when a conflict was detected and recorded,
     * 'applied' on a normal apply, or 'skipped' when a stale update
     * lost to a newer cloud copy (legacy fallback path).
     */
    private function processItem(array $item, ?string $lastSyncedAt = null): string
    {
        $model = match ($item['table']) {
            'wedstrijden' => Wedstrijd::class,
            'judokas' => Judoka::class,
            default => throw new \Exception("Unknown table: {$item['table']}"),
        };

        switch ($item['action']) {
            case 'update':
                $record = $model::find($item['record_id']);
                if (!$record) {
                    throw new \Exception("Record not found: {$item['table']}#{$item['record_id']}");
                }

                $localPayload = $item['payload'];
                $cloudData = $record->toArray();

                // Real conflict detection: BOTH sides modified since last sync.
                // Only available when the local sync client tells us its
                // last successful sync timestamp. Without that we cannot
                // distinguish "stale resend" from "concurrent edit", so we
                // fall back to the legacy last-write-wins behaviour below.
                if ($lastSyncedAt && $this->detectConflict($localPayload, $cloudData, $lastSyncedAt)) {
                    $winner = $this->resolveConflict(
                        $localPayload,
                        $cloudData,
                        $item['table'],
                        $item['record_id'],
                        $record
                    );

                    Log::warning("Sync conflict detected", [
                        'table' => $item['table'],
                        'record_id' => $item['record_id'],
                        'winner' => $winner,
                    ]);

                    return 'conflict';
                }

                // Legacy path: simple last-write-wins on local_updated_at.
                // Kept so older clients (without last_synced_at) keep working.
                $localUpdatedAt = $localPayload['local_updated_at'] ?? null;
                $currentUpdatedAt = $record->local_updated_at ?? $record->updated_at;

                if ($localUpdatedAt && $currentUpdatedAt) {
                    $localTime = Carbon::parse($localUpdatedAt);
                    $currentTime = Carbon::parse($currentUpdatedAt);

                    if ($localTime->lt($currentTime)) {
                        Log::info("Conflict resolved: cloud version is newer", [
                            'table' => $item['table'],
                            'record_id' => $item['record_id'],
                            'local_time' => $localUpdatedAt,
                            'cloud_time' => $currentUpdatedAt,
                        ]);
                        return 'skipped';
                    }
                }

                // Apply update
                $record->update($localPayload);
                return 'applied';

            case 'delete':
                $record = $model::find($item['record_id']);
                $record?->delete();
                return 'applied';

            case 'create':
                // Creates are less common from local - usually updates
                $model::updateOrCreate(
                    ['id' => $item['record_id']],
                    $item['payload']
                );
                return 'applied';
        }

        return 'applied';
    }

    /**
     * A conflict exists when BOTH the local and cloud copies have been
     * modified since the last successful sync. A stale resend (only the
     * local side is older than last sync) is NOT a conflict.
     */
    protected function detectConflict(array $localRecord, array $cloudRecord, string $lastSyncedAt): bool
    {
        $localStamp = $localRecord['local_updated_at'] ?? $localRecord['updated_at'] ?? null;
        $cloudStamp = $cloudRecord['updated_at'] ?? null;

        if (!$localStamp || !$cloudStamp) {
            return false;
        }

        try {
            $localModified = Carbon::parse($localStamp);
            $cloudModified = Carbon::parse($cloudStamp);
            $lastSync = Carbon::parse($lastSyncedAt);
        } catch (\Throwable $e) {
            return false;
        }

        return $localModified->gt($lastSync) && $cloudModified->gt($lastSync);
    }

    /**
     * Record the conflict and apply the winner.
     *
     * Live tournament data (wedstrijden, scores) belongs to the local mat
     * — overruling it from the cloud loses points that were just awarded.
     * Config-style data (judoka, poule setup) belongs to the cloud, where
     * organisators edit names and weights between rounds.
     *
     * @return string winner ('local' or 'cloud')
     */
    protected function resolveConflict(
        array $localRecord,
        array $cloudRecord,
        string $table,
        int $recordId,
        $eloquentRecord
    ): string {
        $winner = SyncConflict::winnerFor($table);

        SyncConflict::create([
            'table_name' => $table,
            'record_id' => $recordId,
            'local_data' => $localRecord,
            'cloud_data' => $cloudRecord,
            'applied_winner' => $winner,
        ]);

        if ($winner === SyncConflict::WINNER_LOCAL) {
            $eloquentRecord->update($localRecord);
        }
        // Cloud winner = leave the existing record alone.

        return $winner;
    }

    // Export helpers

    private function exportClubs(Toernooi $toernooi): array
    {
        return $toernooi->clubs()->get()->toArray();
    }

    private function exportBlokken(Toernooi $toernooi): array
    {
        return $toernooi->blokken()->get()->toArray();
    }

    private function exportMatten(Toernooi $toernooi): array
    {
        return $toernooi->matten()->get()->toArray();
    }

    private function exportJudokas(Toernooi $toernooi): array
    {
        return $toernooi->judokas()->get()->toArray();
    }

    private function exportPoules(Toernooi $toernooi): array
    {
        return $toernooi->poules()
            ->with('judokas:id')
            ->get()
            ->map(function ($poule) {
                $data = $poule->toArray();
                $data['judoka_ids'] = $poule->judokas->pluck('id')->toArray();
                unset($data['judokas']);
                return $data;
            })
            ->toArray();
    }

    private function exportWedstrijden(Toernooi $toernooi): array
    {
        return Wedstrijd::whereHas('poule', function ($q) use ($toernooi) {
            $q->where('toernooi_id', $toernooi->id);
        })->get()->toArray();
    }
}
