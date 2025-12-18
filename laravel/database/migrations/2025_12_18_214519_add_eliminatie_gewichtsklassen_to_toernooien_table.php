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
            // Per leeftijdsklasse: welke gewichtsklassen gebruiken eliminatie
            // Format: {"heren_15": ["-46", "-50"], "dames_15": ["-44"]}
            $table->json('eliminatie_gewichtsklassen')->nullable()->after('wedstrijd_systeem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('eliminatie_gewichtsklassen');
        });
    }
};
