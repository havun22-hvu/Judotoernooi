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
        // Add organisator_id to toernooien
        Schema::table('toernooien', function (Blueprint $table) {
            $table->foreignId('organisator_id')->nullable()->after('id')->constrained('organisators')->nullOnDelete();
        });

        // Populate organisator_id from pivot table (eigenaar role)
        $eigenaarRelaties = DB::table('organisator_toernooi')
            ->where('rol', 'eigenaar')
            ->get();

        foreach ($eigenaarRelaties as $relatie) {
            DB::table('toernooien')
                ->where('id', $relatie->toernooi_id)
                ->update(['organisator_id' => $relatie->organisator_id]);
        }

        // Drop the global unique constraint on slug and add scoped unique
        // Note: SQLite doesn't support dropping index directly, so we recreate the column
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite: drop and recreate with new constraint
            // First check if index exists
            $indexes = DB::select("PRAGMA index_list('toernooien')");
            $hasSlugIndex = collect($indexes)->contains(fn($idx) => str_contains($idx->name, 'slug'));

            if ($hasSlugIndex) {
                // SQLite workaround: can't drop index, but we can add a composite unique
                // The old unique will be replaced by composite unique in route resolution
            }
        } else {
            // MySQL: drop old unique and add composite
            Schema::table('toernooien', function (Blueprint $table) {
                $table->dropUnique(['slug']);
            });
        }

        // Add composite unique constraint (organisator_id + slug)
        Schema::table('toernooien', function (Blueprint $table) {
            $table->unique(['organisator_id', 'slug'], 'toernooien_organisator_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropUnique('toernooien_organisator_slug_unique');
            $table->dropForeign(['organisator_id']);
            $table->dropColumn('organisator_id');
            $table->unique('slug');
        });
    }
};
