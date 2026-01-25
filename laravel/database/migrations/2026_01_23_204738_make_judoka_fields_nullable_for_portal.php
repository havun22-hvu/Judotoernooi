<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SQLite doesn't support ALTER COLUMN, so we recreate the table.
     * This makes geboortejaar, geslacht, band, leeftijdsklasse, gewichtsklasse nullable
     * so coaches can add judokas with incomplete data via the portal.
     *
     * IMPORTANT: We disable FK checks and backup/restore dependent tables to prevent
     * the "judokas_backup table not found" error that occurs when SQLite renames
     * FK constraints along with the table.
     */
    public function up(): void
    {
        // SQLite workaround: recreate table with nullable columns
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Disable FK checks to prevent cascade issues
            DB::statement('PRAGMA foreign_keys = OFF');

            try {
                // Backup data from dependent tables BEFORE touching judokas
                $wedstrijdenData = DB::table('wedstrijden')->get()->toArray();
                $pouleJudokaData = DB::table('poule_judoka')->get()->toArray();
                $wegingenData = Schema::hasTable('wegingen') ? DB::table('wegingen')->get()->toArray() : [];

                // Backup judokas data
                $judokasData = DB::table('judokas')->get()->toArray();

                // Drop dependent tables first (they have FK to judokas)
                Schema::dropIfExists('wedstrijden');
                Schema::dropIfExists('poule_judoka');
                if (Schema::hasTable('wegingen')) {
                    Schema::dropIfExists('wegingen');
                }

                // Drop indexes on judokas
                try {
                    DB::statement('DROP INDEX IF EXISTS judokas_toernooi_id_leeftijdsklasse_gewichtsklasse_index');
                    DB::statement('DROP INDEX IF EXISTS judokas_toernooi_id_judoka_code_index');
                    DB::statement('DROP INDEX IF EXISTS judokas_qr_code_unique');
                } catch (\Exception $e) {
                    // Indexes may not exist, continue
                }

                // Now we can safely drop and recreate judokas
                Schema::dropIfExists('judokas');

                // Create new judokas table with nullable columns
                Schema::create('judokas', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
                    $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();

                    // Persoonlijke gegevens - NOW NULLABLE for portal incomplete entries
                    $table->string('naam');
                    $table->string('voornaam')->nullable();
                    $table->string('achternaam')->nullable();
                    $table->year('geboortejaar')->nullable();
                    $table->string('geslacht', 1)->nullable();
                    $table->string('band', 20)->nullable();
                    $table->decimal('gewicht', 4, 1)->nullable();

                    // Berekende classificatie - NOW NULLABLE
                    $table->string('leeftijdsklasse', 20)->nullable();
                    $table->string('gewichtsklasse', 10)->nullable();
                    $table->string('judoka_code', 20)->nullable();

                    // Toernooidag
                    $table->string('aanwezigheid', 20)->default('onbekend');
                    $table->decimal('gewicht_gewogen', 4, 1)->nullable();
                    $table->string('opmerking')->nullable();

                    // QR code for check-in
                    $table->string('qr_code', 50)->nullable()->unique();

                    // Sync status for portal
                    $table->timestamp('synced_at')->nullable();

                    // Import warnings
                    $table->text('import_warnings')->nullable();
                    $table->string('import_status', 20)->nullable();

                    // Telefoon for WhatsApp
                    $table->string('telefoon', 30)->nullable();

                    // Sort fields
                    $table->string('sort_categorie', 50)->nullable();
                    $table->decimal('sort_gewicht', 5, 1)->nullable();
                    $table->string('sort_band', 20)->nullable();
                    $table->string('categorie_key', 50)->nullable();

                    // Incomplete data flag
                    $table->boolean('is_onvolledig')->default(false);

                    // Betaling
                    $table->foreignId('betaling_id')->nullable()->constrained('betalingen')->nullOnDelete();

                    // Overpouled
                    $table->foreignId('overpouled_van_poule_id')->nullable();

                    $table->timestamps();

                    $table->index(['toernooi_id', 'leeftijdsklasse', 'gewichtsklasse']);
                    $table->index(['toernooi_id', 'judoka_code']);
                });

                // Restore judokas data
                foreach ($judokasData as $judoka) {
                    DB::table('judokas')->insert((array) $judoka);
                }

                // Recreate dependent tables with correct FK references
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

                // Restore data to dependent tables
                foreach ($pouleJudokaData as $row) {
                    DB::table('poule_judoka')->insert((array) $row);
                }
                foreach ($wedstrijdenData as $row) {
                    DB::table('wedstrijden')->insert((array) $row);
                }
                foreach ($wegingenData as $row) {
                    DB::table('wegingen')->insert((array) $row);
                }
            } finally {
                // Re-enable FK checks
                DB::statement('PRAGMA foreign_keys = ON');
            }
        } else {
            // MySQL/PostgreSQL: use normal column modification
            Schema::table('judokas', function (Blueprint $table) {
                $table->year('geboortejaar')->nullable()->change();
                $table->string('geslacht', 1)->nullable()->change();
                $table->string('band', 20)->nullable()->change();
                $table->string('leeftijdsklasse', 20)->nullable()->change();
                $table->string('gewichtsklasse', 10)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For rollback, we'd need to handle null values first
        // This is a one-way migration for simplicity
    }
};
