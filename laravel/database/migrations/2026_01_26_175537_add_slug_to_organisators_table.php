<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('naam');
        });

        // Generate slugs for existing organisators
        $organisators = DB::table('organisators')->get();
        foreach ($organisators as $organisator) {
            $baseSlug = Str::slug($organisator->naam);
            $slug = $baseSlug;
            $counter = 1;

            while (DB::table('organisators')->where('slug', $slug)->where('id', '!=', $organisator->id)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            DB::table('organisators')->where('id', $organisator->id)->update(['slug' => $slug]);
        }

        // Make slug required after filling existing records
        Schema::table('organisators', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
