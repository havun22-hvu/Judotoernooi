<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            // Per age class: competition format
            // Format: {"minis": "poules", "a_pupillen": "poules_kruisfinale", "heren": "eliminatie"}
            // Options: poules, poules_kruisfinale, eliminatie
            $table->json('wedstrijd_systeem')->nullable()->after('clubspreiding');
            // How many places go to kruisfinale (1 = only winners, 2 = top 2, 3 = top 3)
            $table->unsignedTinyInteger('kruisfinales_aantal')->default(1)->after('wedstrijd_systeem');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn(['wedstrijd_systeem', 'kruisfinales_aantal']);
        });
    }
};
