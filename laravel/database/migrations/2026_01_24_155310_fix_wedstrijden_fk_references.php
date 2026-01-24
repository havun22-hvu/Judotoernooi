<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix all tables with FK references to judokas_backup.
     *
     * Problem: SQLite renamed FK constraints when judokas table was renamed to judokas_backup.
     * The FKs now point to judokas_backup which doesn't exist.
     *
     * Affected tables: wedstrijden, poule_judoka, wegingen
     *
     * Solution: Recreate tables with correct FK references to judokas.
     * Data is preserved by backing up and restoring.
     */
    public function up(): void
    {
        // Only fix for SQLite (MySQL doesn't have this problem)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        // Disable FK checks during migration
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            $this->fixWedstrijden();
            $this->fixPouleJudoka();
            $this->fixWegingen();
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    private function fixWedstrijden(): void
    {
        $schema = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='wedstrijden'");
        if (empty($schema) || !str_contains($schema[0]->sql ?? '', 'judokas_backup')) {
            return;
        }

        $data = DB::table('wedstrijden')->get();
        Schema::dropIfExists('wedstrijden');

        Schema::create('wedstrijden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poule_id')->constrained('poules')->cascadeOnDelete();
            $table->foreignId('judoka_wit_id')->nullable()->constrained('judokas')->nullOnDelete();
            $table->foreignId('judoka_blauw_id')->nullable()->constrained('judokas')->nullOnDelete();
            $table->integer('volgorde');
            $table->foreignId('winnaar_id')->nullable()->constrained('judokas')->nullOnDelete();
            $table->string('score_wit')->nullable();
            $table->string('score_blauw')->nullable();
            $table->string('uitslag_type')->nullable();
            $table->boolean('is_gespeeld')->default(false);
            $table->datetime('gespeeld_op')->nullable();
            $table->timestamps();
            $table->string('ronde')->nullable();
            $table->string('groep')->nullable();
            $table->integer('bracket_positie')->nullable();
            $table->foreignId('volgende_wedstrijd_id')->nullable();
            $table->foreignId('herkansing_wedstrijd_id')->nullable();
            $table->integer('winnaar_naar_slot')->nullable();
            $table->integer('verliezer_naar_slot')->nullable();
            $table->integer('locatie_wit')->nullable();
            $table->integer('locatie_blauw')->nullable();
        });

        foreach ($data as $row) {
            DB::table('wedstrijden')->insert((array) $row);
        }
    }

    private function fixPouleJudoka(): void
    {
        $schema = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='poule_judoka'");
        if (empty($schema) || !str_contains($schema[0]->sql ?? '', 'judokas_backup')) {
            return;
        }

        $data = DB::table('poule_judoka')->get();
        Schema::dropIfExists('poule_judoka');

        Schema::create('poule_judoka', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poule_id')->constrained('poules')->cascadeOnDelete();
            $table->foreignId('judoka_id')->constrained('judokas')->cascadeOnDelete();
            $table->integer('positie')->nullable();
            $table->integer('punten')->default(0);
            $table->integer('gewonnen')->default(0);
            $table->integer('verloren')->default(0);
            $table->integer('gelijk')->default(0);
            $table->integer('eindpositie')->nullable();
            $table->timestamps();
        });

        foreach ($data as $row) {
            DB::table('poule_judoka')->insert((array) $row);
        }
    }

    private function fixWegingen(): void
    {
        $schema = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='wegingen'");
        if (empty($schema) || !str_contains($schema[0]->sql ?? '', 'judokas_backup')) {
            return;
        }

        $data = DB::table('wegingen')->get();
        Schema::dropIfExists('wegingen');

        Schema::create('wegingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judoka_id')->constrained('judokas')->cascadeOnDelete();
            $table->decimal('gewicht', 4, 1);
            $table->boolean('binnen_klasse')->default(true);
            $table->string('alternatieve_poule')->nullable();
            $table->string('opmerking')->nullable();
            $table->string('geregistreerd_door')->nullable();
            $table->timestamps();
        });

        foreach ($data as $row) {
            DB::table('wegingen')->insert((array) $row);
        }
    }

    public function down(): void
    {
        // No rollback - this is a fix migration
    }
};
