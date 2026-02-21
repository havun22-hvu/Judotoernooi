<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add wimpel columns to stam_judokas
        Schema::table('stam_judokas', function (Blueprint $table) {
            $table->unsignedInteger('wimpel_punten_totaal')->default(0)->after('notities');
            $table->boolean('wimpel_is_nieuw')->default(false)->after('wimpel_punten_totaal');
        });

        // 2. Copy data from wimpel_judokas to stam_judokas
        if (Schema::hasTable('wimpel_judokas')) {
            // 2a. Linked wimpel_judokas (have stam_judoka_id)
            $linked = DB::table('wimpel_judokas')->whereNotNull('stam_judoka_id')->get();
            foreach ($linked as $wj) {
                DB::table('stam_judokas')->where('id', $wj->stam_judoka_id)->update([
                    'wimpel_punten_totaal' => $wj->punten_totaal,
                    'wimpel_is_nieuw' => $wj->is_nieuw,
                ]);
            }

            // 2b. Unlinked wimpel_judokas: match by naam+geboortejaar or create stam_judoka
            $unlinked = DB::table('wimpel_judokas')->whereNull('stam_judoka_id')->get();
            foreach ($unlinked as $wj) {
                $stam = DB::table('stam_judokas')
                    ->where('organisator_id', $wj->organisator_id)
                    ->where('naam', $wj->naam)
                    ->where('geboortejaar', $wj->geboortejaar)
                    ->first();

                if ($stam) {
                    $stamId = $stam->id;
                    // Only update if stam has no wimpel data yet
                    if ($stam->wimpel_punten_totaal == 0) {
                        DB::table('stam_judokas')->where('id', $stamId)->update([
                            'wimpel_punten_totaal' => $wj->punten_totaal,
                            'wimpel_is_nieuw' => $wj->is_nieuw,
                        ]);
                    }
                } else {
                    $stamId = DB::table('stam_judokas')->insertGetId([
                        'organisator_id' => $wj->organisator_id,
                        'naam' => $wj->naam,
                        'geboortejaar' => $wj->geboortejaar,
                        'geslacht' => 'M',
                        'band' => 'wit',
                        'actief' => true,
                        'wimpel_punten_totaal' => $wj->punten_totaal,
                        'wimpel_is_nieuw' => $wj->is_nieuw,
                        'created_at' => $wj->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                }

                // Update the wimpel_judoka with stam_judoka_id for FK remapping
                DB::table('wimpel_judokas')->where('id', $wj->id)->update([
                    'stam_judoka_id' => $stamId,
                ]);
            }

            // 3. Remap FK's in wimpel_punten_log and wimpel_uitreikingen
            // Add stam_judoka_id column to both tables
            Schema::table('wimpel_punten_log', function (Blueprint $table) {
                $table->unsignedBigInteger('stam_judoka_id')->nullable()->after('id');
            });

            Schema::table('wimpel_uitreikingen', function (Blueprint $table) {
                $table->unsignedBigInteger('stam_judoka_id')->nullable()->after('id');
            });

            // Copy FK mapping: wimpel_judoka_id -> stam_judoka_id
            $mapping = DB::table('wimpel_judokas')->pluck('stam_judoka_id', 'id');
            foreach ($mapping as $wimpelId => $stamId) {
                DB::table('wimpel_punten_log')
                    ->where('wimpel_judoka_id', $wimpelId)
                    ->update(['stam_judoka_id' => $stamId]);

                DB::table('wimpel_uitreikingen')
                    ->where('wimpel_judoka_id', $wimpelId)
                    ->update(['stam_judoka_id' => $stamId]);
            }

            // Drop old FK columns and add proper FK constraints
            Schema::table('wimpel_punten_log', function (Blueprint $table) {
                $table->dropForeign(['wimpel_judoka_id']);
                $table->dropColumn('wimpel_judoka_id');
                $table->foreign('stam_judoka_id')->references('id')->on('stam_judokas')->cascadeOnDelete();
            });

            // Drop FK first (MySQL requires FK dropped before unique index)
            Schema::table('wimpel_uitreikingen', function (Blueprint $table) {
                $table->dropForeign(['wimpel_judoka_id']);
            });
            Schema::table('wimpel_uitreikingen', function (Blueprint $table) {
                $table->dropUnique('wimpel_uitreik_judoka_milestone_unique');
                $table->dropColumn('wimpel_judoka_id');
                $table->foreign('stam_judoka_id')->references('id')->on('stam_judokas')->cascadeOnDelete();
                $table->unique(['stam_judoka_id', 'wimpel_milestone_id'], 'wimpel_uitreik_stam_milestone_unique');
            });

            // 4. Drop wimpel_judokas table
            Schema::dropIfExists('wimpel_judokas');
        }
    }

    public function down(): void
    {
        // Recreate wimpel_judokas table
        Schema::create('wimpel_judokas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisator_id')->constrained('organisators')->cascadeOnDelete();
            $table->foreignId('stam_judoka_id')->nullable()->constrained('stam_judokas')->nullOnDelete();
            $table->string('naam');
            $table->unsignedSmallInteger('geboortejaar');
            $table->unsignedInteger('punten_totaal')->default(0);
            $table->boolean('is_nieuw')->default(true);
            $table->timestamps();
            $table->unique(['organisator_id', 'naam', 'geboortejaar']);
        });

        // Reverse FK remapping would be complex - skip for down migration
        Schema::table('stam_judokas', function (Blueprint $table) {
            $table->dropColumn(['wimpel_punten_totaal', 'wimpel_is_nieuw']);
        });
    }
};
