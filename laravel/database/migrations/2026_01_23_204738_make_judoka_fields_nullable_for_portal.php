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
     */
    public function up(): void
    {
        // SQLite workaround: recreate table with nullable columns
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Get existing data
            $judokas = DB::table('judokas')->get();

            // Drop indexes first (SQLite keeps them when renaming)
            try {
                DB::statement('DROP INDEX IF EXISTS judokas_toernooi_id_leeftijdsklasse_gewichtsklasse_index');
                DB::statement('DROP INDEX IF EXISTS judokas_toernooi_id_judoka_code_index');
                DB::statement('DROP INDEX IF EXISTS judokas_qr_code_unique');
            } catch (\Exception $e) {
                // Indexes may not exist, continue
            }

            Schema::dropIfExists('judokas_backup');

            // Rename old table
            DB::statement('ALTER TABLE judokas RENAME TO judokas_backup');

            // Create new table with nullable columns
            Schema::create('judokas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
                $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();

                // Persoonlijke gegevens - NOW NULLABLE for portal incomplete entries
                $table->string('naam');
                $table->string('voornaam')->nullable();
                $table->string('achternaam')->nullable();
                $table->year('geboortejaar')->nullable(); // WAS required
                $table->string('geslacht', 1)->nullable(); // WAS required
                $table->string('band', 20)->nullable(); // WAS required
                $table->decimal('gewicht', 4, 1)->nullable();

                // Berekende classificatie - NOW NULLABLE
                $table->string('leeftijdsklasse', 20)->nullable(); // WAS required
                $table->string('gewichtsklasse', 10)->nullable(); // WAS required
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

            // Copy data back
            foreach ($judokas as $judoka) {
                DB::table('judokas')->insert((array) $judoka);
            }

            // Drop backup table
            Schema::dropIfExists('judokas_backup');
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
