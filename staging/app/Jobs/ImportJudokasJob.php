<?php

namespace App\Jobs;

use App\Models\Toernooi;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Background job for importing large judoka lists.
 * Provides progress tracking via cache.
 */
class ImportJudokasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes max
    public int $tries = 1; // No retries - import should be atomic

    private string $importId;

    public function __construct(
        private Toernooi $toernooi,
        private array $rows,
        private array $mapping,
        private array $header
    ) {
        $this->importId = 'import_' . $toernooi->id . '_' . time();
    }

    /**
     * Get the import ID for progress tracking.
     */
    public function getImportId(): string
    {
        return $this->importId;
    }

    public function handle(ImportService $importService): void
    {
        $total = count($this->rows);
        $processed = 0;
        $errors = [];

        $this->updateProgress(0, $total, 'starting');

        try {
            // Build column mapping (convert indices to header names)
            $kolomMapping = [];
            foreach ($this->mapping as $veld => $kolomIndex) {
                if ($kolomIndex !== null && $kolomIndex !== '') {
                    if (str_contains((string)$kolomIndex, ',')) {
                        $kolomMapping[$veld] = $kolomIndex;
                    } elseif (isset($this->header[$kolomIndex])) {
                        $kolomMapping[$veld] = $this->header[$kolomIndex];
                    }
                }
            }

            // Convert to associative array
            $headerCount = count($this->header);
            $associativeRows = array_map(function ($row) use ($headerCount) {
                $row = array_pad((array) $row, $headerCount, null);
                return array_combine($this->header, array_slice($row, 0, $headerCount));
            }, $this->rows);

            // Import in batches for progress tracking
            $batchSize = 50;
            $batches = array_chunk($associativeRows, $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                $result = $importService->importeerJudokas(
                    $this->toernooi,
                    $batch,
                    $kolomMapping
                );

                $processed += count($batch);
                $this->updateProgress($processed, $total, 'processing');

                // Small delay to prevent overwhelming the database
                if ($batchIndex < count($batches) - 1) {
                    usleep(100000); // 0.1 second
                }
            }

            $this->updateProgress($total, $total, 'completed');

            Log::info('Import completed', [
                'toernooi_id' => $this->toernooi->id,
                'imported' => $processed,
            ]);

        } catch (\Exception $e) {
            $this->updateProgress($processed, $total, 'failed', $e->getMessage());

            Log::error('Import failed', [
                'toernooi_id' => $this->toernooi->id,
                'error' => $e->getMessage(),
                'processed' => $processed,
            ]);

            throw $e;
        }
    }

    /**
     * Update import progress in cache.
     */
    private function updateProgress(int $processed, int $total, string $status, ?string $error = null): void
    {
        Cache::put($this->importId, [
            'processed' => $processed,
            'total' => $total,
            'percentage' => $total > 0 ? round(($processed / $total) * 100) : 0,
            'status' => $status,
            'error' => $error,
            'updated_at' => now()->toIso8601String(),
        ], 3600); // Keep for 1 hour
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $e): void
    {
        $this->updateProgress(0, count($this->rows), 'failed', $e->getMessage());

        Log::error('Import job failed', [
            'toernooi_id' => $this->toernooi->id,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Get current progress for an import.
     */
    public static function getProgress(string $importId): ?array
    {
        return Cache::get($importId);
    }
}
