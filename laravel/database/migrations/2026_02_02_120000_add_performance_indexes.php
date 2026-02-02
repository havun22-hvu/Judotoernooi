<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes for commonly queried columns to improve performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Poules: filter by leeftijdsklasse/gewichtsklasse
        Schema::table('poules', function (Blueprint $table) {
            $table->index('leeftijdsklasse');
            $table->index('gewichtsklasse');
            $table->index('type'); // poule/eliminatie filtering
        });

        // Judokas: filter by club, aanwezigheid
        Schema::table('judokas', function (Blueprint $table) {
            $table->index('club_id');
            $table->index('aanwezigheid');
            $table->index('geslacht');
        });

        // Wedstrijden: filter by is_gespeeld, winnaar lookup
        Schema::table('wedstrijden', function (Blueprint $table) {
            $table->index('is_gespeeld');
            $table->index('winnaar_id');
        });

        // Poule_judoka: reverse lookup by judoka
        Schema::table('poule_judoka', function (Blueprint $table) {
            $table->index('judoka_id');
        });
    }

    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->dropIndex(['leeftijdsklasse']);
            $table->dropIndex(['gewichtsklasse']);
            $table->dropIndex(['type']);
        });

        Schema::table('judokas', function (Blueprint $table) {
            $table->dropIndex(['club_id']);
            $table->dropIndex(['aanwezigheid']);
            $table->dropIndex(['geslacht']);
        });

        Schema::table('wedstrijden', function (Blueprint $table) {
            $table->dropIndex(['is_gespeeld']);
            $table->dropIndex(['winnaar_id']);
        });

        Schema::table('poule_judoka', function (Blueprint $table) {
            $table->dropIndex(['judoka_id']);
        });
    }
};
