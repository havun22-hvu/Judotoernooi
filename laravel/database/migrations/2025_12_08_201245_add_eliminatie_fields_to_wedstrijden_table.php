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
        Schema::table('wedstrijden', function (Blueprint $table) {
            // Eliminatie specific fields
            $table->string('ronde', 30)->nullable()->after('volgorde');
            // finale, halve_finale, kwartfinale, achtste_finale, brons, herkansing_r1, etc.

            $table->char('groep', 1)->nullable()->after('ronde');
            // A = hoofdboom, B = herkansing

            $table->unsignedSmallInteger('bracket_positie')->nullable()->after('groep');
            // Position in the bracket (1-based)

            // Self-referencing foreign keys for bracket progression
            $table->foreignId('volgende_wedstrijd_id')->nullable()->after('bracket_positie')
                ->constrained('wedstrijden')->nullOnDelete();
            // Winner goes to this match

            $table->foreignId('herkansing_wedstrijd_id')->nullable()->after('volgende_wedstrijd_id')
                ->constrained('wedstrijden')->nullOnDelete();
            // Loser goes to this match (repechage)

            // Track which slot (wit/blauw) the winner/loser fills in next match
            $table->char('winnaar_naar_slot', 5)->nullable()->after('herkansing_wedstrijd_id');
            // 'wit' or 'blauw'

            $table->char('verliezer_naar_slot', 5)->nullable()->after('winnaar_naar_slot');
            // 'wit' or 'blauw'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wedstrijden', function (Blueprint $table) {
            $table->dropForeign(['volgende_wedstrijd_id']);
            $table->dropForeign(['herkansing_wedstrijd_id']);
            $table->dropColumn([
                'ronde',
                'groep',
                'bracket_positie',
                'volgende_wedstrijd_id',
                'herkansing_wedstrijd_id',
                'winnaar_naar_slot',
                'verliezer_naar_slot',
            ]);
        });
    }
};
