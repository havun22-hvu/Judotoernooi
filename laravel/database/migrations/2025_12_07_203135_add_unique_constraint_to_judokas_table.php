<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, merge any existing duplicates (keep the one with most data)
        $this->mergeDuplicates();

        // Then add unique constraint
        Schema::table('judokas', function (Blueprint $table) {
            $table->unique(['toernooi_id', 'naam', 'geboortejaar'], 'judokas_unique_per_toernooi');
        });
    }

    /**
     * Find and merge duplicate judokas before adding constraint
     */
    private function mergeDuplicates(): void
    {
        // Find duplicates (same toernooi, naam, geboortejaar)
        $duplicates = DB::table('judokas')
            ->select('toernooi_id', 'naam', 'geboortejaar', DB::raw('COUNT(*) as count'))
            ->groupBy('toernooi_id', 'naam', 'geboortejaar')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            // Get all records for this duplicate
            $records = DB::table('judokas')
                ->where('toernooi_id', $dup->toernooi_id)
                ->where('naam', $dup->naam)
                ->where('geboortejaar', $dup->geboortejaar)
                ->orderByRaw('CASE WHEN gewicht IS NOT NULL THEN 0 ELSE 1 END') // Prefer with weight
                ->orderBy('id')
                ->get();

            // Keep first (best) record, delete others
            $keepId = $records->first()->id;
            $deleteIds = $records->skip(1)->pluck('id')->toArray();

            if (!empty($deleteIds)) {
                // Move poule associations to kept record (ignore if already exists)
                foreach ($deleteIds as $deleteId) {
                    $pouleIds = DB::table('poule_judoka')
                        ->where('judoka_id', $deleteId)
                        ->pluck('poule_id');

                    foreach ($pouleIds as $pouleId) {
                        // Check if association already exists
                        $exists = DB::table('poule_judoka')
                            ->where('poule_id', $pouleId)
                            ->where('judoka_id', $keepId)
                            ->exists();

                        if (!$exists) {
                            DB::table('poule_judoka')
                                ->where('poule_id', $pouleId)
                                ->where('judoka_id', $deleteId)
                                ->update(['judoka_id' => $keepId]);
                        } else {
                            DB::table('poule_judoka')
                                ->where('poule_id', $pouleId)
                                ->where('judoka_id', $deleteId)
                                ->delete();
                        }
                    }
                }

                // Delete duplicate judokas
                DB::table('judokas')->whereIn('id', $deleteIds)->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropUnique('judokas_unique_per_toernooi');
        });
    }
};
