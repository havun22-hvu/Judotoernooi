<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
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
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.table' => 'required|string|in:wedstrijden,judokas',
            'items.*.record_id' => 'required|integer',
            'items.*.action' => 'required|string|in:create,update,delete',
            'items.*.payload' => 'required|array',
        ]);

        $synced = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($validated['items'] as $item) {
                try {
                    $this->processItem($item);
                    $synced[] = $item['id'];
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
            'received_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Process a single sync item
     */
    private function processItem(array $item): void
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

                // Conflict resolution: last-write-wins based on local_updated_at
                $localUpdatedAt = $item['payload']['local_updated_at'] ?? null;
                $currentUpdatedAt = $record->local_updated_at ?? $record->updated_at;

                if ($localUpdatedAt && $currentUpdatedAt) {
                    $localTime = \Carbon\Carbon::parse($localUpdatedAt);
                    $currentTime = \Carbon\Carbon::parse($currentUpdatedAt);

                    if ($localTime->lt($currentTime)) {
                        Log::info("Conflict resolved: cloud version is newer", [
                            'table' => $item['table'],
                            'record_id' => $item['record_id'],
                            'local_time' => $localUpdatedAt,
                            'cloud_time' => $currentUpdatedAt,
                        ]);
                        return; // Skip this update, cloud has newer data
                    }
                }

                // Apply update
                $record->update($item['payload']);
                break;

            case 'delete':
                $record = $model::find($item['record_id']);
                $record?->delete();
                break;

            case 'create':
                // Creates are less common from local - usually updates
                $model::updateOrCreate(
                    ['id' => $item['record_id']],
                    $item['payload']
                );
                break;
        }
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
