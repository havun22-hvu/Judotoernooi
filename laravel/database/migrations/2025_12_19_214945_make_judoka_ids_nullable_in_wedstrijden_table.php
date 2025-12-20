<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Make judoka IDs nullable for elimination matches where judoka is TBD (bye or not yet determined)
     */
    public function up(): void
    {
        // SQLite doesn't support modifying columns directly, so we need to recreate the table
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // For SQLite: create new table, copy data, drop old, rename new
            Schema::create('wedstrijden_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('poule_id')->constrained('poules')->cascadeOnDelete();
                $table->foreignId('judoka_wit_id')->nullable()->constrained('judokas')->nullOnDelete();
                $table->foreignId('judoka_blauw_id')->nullable()->constrained('judokas')->nullOnDelete();
                $table->unsignedTinyInteger('volgorde');
                $table->foreignId('winnaar_id')->nullable()->constrained('judokas')->nullOnDelete();
                $table->string('score_wit', 20)->nullable();
                $table->string('score_blauw', 20)->nullable();
                $table->string('uitslag_type', 20)->nullable();
                $table->boolean('is_gespeeld')->default(false);
                $table->timestamp('gespeeld_op')->nullable();
                $table->timestamps();
                $table->string('ronde', 30)->nullable();
                $table->string('groep', 10)->nullable();
                $table->unsignedInteger('bracket_positie')->nullable();
                $table->foreignId('volgende_wedstrijd_id')->nullable();
                $table->foreignId('herkansing_wedstrijd_id')->nullable();
                $table->unsignedInteger('winnaar_naar_slot')->nullable();
                $table->unsignedInteger('verliezer_naar_slot')->nullable();
            });

            // Copy data
            \DB::statement('INSERT INTO wedstrijden_new SELECT * FROM wedstrijden');

            // Drop old table and rename new
            Schema::drop('wedstrijden');
            Schema::rename('wedstrijden_new', 'wedstrijden');
        } else {
            // For MySQL/PostgreSQL
            Schema::table('wedstrijden', function (Blueprint $table) {
                $table->foreignId('judoka_wit_id')->nullable()->change();
                $table->foreignId('judoka_blauw_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This will fail if there are NULL values in the columns
        Schema::table('wedstrijden', function (Blueprint $table) {
            $table->foreignId('judoka_wit_id')->nullable(false)->change();
            $table->foreignId('judoka_blauw_id')->nullable(false)->change();
        });
    }
};
