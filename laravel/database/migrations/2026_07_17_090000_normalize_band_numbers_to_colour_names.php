<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert legacy numeric belts ("0".."6") to the colour names the Band enum prescribes.
 *
 * The numbers come from the old Google Apps Script system (zwart=0 .. wit=6) and broke two
 * things: empty("0") is true, so a black belt was reported as a missing belt, and
 * DynamischeIndelingService::bandNaarNummer() maps on colour keys and fell back to wit for
 * every numeric value, silently disabling max_band_verschil in the poule solver.
 *
 * Idempotent: only rows that are exactly "0".."6" are touched, so a re-run is a no-op.
 */
return new class extends Migration
{
    private const TABELLEN = ['judokas', 'stam_judokas'];

    /**
     * [legacy enum value, stored colour name] — a list, not a map.
     *
     * PHP silently casts numeric array keys to int, which would make this `WHERE band = 0`.
     * MySQL then casts every belt to a number to compare, 'groen' becomes 0, and the update
     * would rewrite every colour name to zwart. Keep the value a string, always.
     */
    private const NUMMER_NAAR_KLEUR = [
        ['0', 'zwart'],
        ['1', 'bruin'],
        ['2', 'blauw'],
        ['3', 'groen'],
        ['4', 'oranje'],
        ['5', 'geel'],
        ['6', 'wit'],
    ];

    public function up(): void
    {
        foreach (self::TABELLEN as $tabel) {
            if (!$this->tabelHeeftBand($tabel)) {
                continue;
            }

            foreach (self::NUMMER_NAAR_KLEUR as [$nummer, $kleur]) {
                // (string) is load-bearing: binding an int makes MySQL compare numerically,
                // which matches every colour name too.
                DB::table($tabel)->where('band', (string) $nummer)->update(['band' => $kleur]);
            }
        }
    }

    /**
     * Deliberately empty: rolling back would reintroduce the falsy "0" that caused the bug,
     * and the colour names this migration writes are what the code has always specified.
     */
    public function down(): void
    {
    }

    private function tabelHeeftBand(string $tabel): bool
    {
        return Schema::hasTable($tabel) && Schema::hasColumn($tabel, 'band');
    }
};
