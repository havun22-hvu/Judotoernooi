<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the database column (only if old name exists)
        if (Schema::hasColumn('toernooien', 'klok_poule_wedstrijden')) {
            Schema::table('toernooien', function (Blueprint $table) {
                $table->renameColumn('klok_poule_wedstrijden', 'punten_competitie_wedstrijden');
            });
        }

        // Update wedstrijd_systeem JSON values: klok_poule -> punten_competitie
        DB::table('toernooien')->whereNotNull('wedstrijd_systeem')->orderBy('id')->each(function ($toernooi) {
            $systeem = json_decode($toernooi->wedstrijd_systeem, true);
            if (!is_array($systeem)) return;

            $changed = false;
            foreach ($systeem as $key => $value) {
                if ($value === 'klok_poule') {
                    $systeem[$key] = 'punten_competitie';
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('toernooien')
                    ->where('id', $toernooi->id)
                    ->update(['wedstrijd_systeem' => json_encode($systeem)]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('toernooien', 'punten_competitie_wedstrijden')) {
            Schema::table('toernooien', function (Blueprint $table) {
                $table->renameColumn('punten_competitie_wedstrijden', 'klok_poule_wedstrijden');
            });
        }

        // Revert wedstrijd_systeem JSON values: punten_competitie -> klok_poule
        DB::table('toernooien')->whereNotNull('wedstrijd_systeem')->orderBy('id')->each(function ($toernooi) {
            $systeem = json_decode($toernooi->wedstrijd_systeem, true);
            if (!is_array($systeem)) return;

            $changed = false;
            foreach ($systeem as $key => $value) {
                if ($value === 'punten_competitie') {
                    $systeem[$key] = 'klok_poule';
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('toernooien')
                    ->where('id', $toernooi->id)
                    ->update(['wedstrijd_systeem' => json_encode($systeem)]);
            }
        });
    }
};
