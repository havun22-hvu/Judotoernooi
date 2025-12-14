<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            // 'gewicht_band' = leeftijd-gewicht-band-volgnummer (default)
            // 'band_gewicht' = leeftijd-band-gewicht-volgnummer
            $table->string('judoka_code_volgorde', 20)->default('gewicht_band')->after('gewicht_tolerantie');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('judoka_code_volgorde');
        });
    }
};
