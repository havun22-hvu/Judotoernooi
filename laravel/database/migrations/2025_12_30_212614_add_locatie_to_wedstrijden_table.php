<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Locaties voor bracket flow:
     * - locatie_wit: oneven (1, 3, 5, ...) - B-winnaars komen hier
     * - locatie_blauw: even (2, 4, 6, ...) - A-verliezers komen hier in B-groep
     *
     * Flow: locatie X en X+1 strijden, winnaar → locatie ceil(X/2)
     */
    public function up(): void
    {
        Schema::table('wedstrijden', function (Blueprint $table) {
            $table->unsignedSmallInteger('locatie_wit')->nullable()->after('bracket_positie');
            $table->unsignedSmallInteger('locatie_blauw')->nullable()->after('locatie_wit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wedstrijden', function (Blueprint $table) {
            $table->dropColumn(['locatie_wit', 'locatie_blauw']);
        });
    }
};
