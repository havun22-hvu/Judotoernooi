<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            // actieve_wedstrijd_id = de wedstrijd die nu gespeeld wordt (groen)
            // huidige_wedstrijd_id = de volgende wedstrijd (geel) - blijft bestaan
            $table->foreignId('actieve_wedstrijd_id')->nullable()->after('huidige_wedstrijd_id');
        });
    }

    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->dropColumn('actieve_wedstrijd_id');
        });
    }
};
