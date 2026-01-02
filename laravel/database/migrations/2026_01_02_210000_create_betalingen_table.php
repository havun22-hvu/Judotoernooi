<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nieuwe velden in toernooien
        Schema::table('toernooien', function (Blueprint $table) {
            $table->boolean('betaling_actief')->default(false)->after('is_actief');
            $table->decimal('inschrijfgeld', 8, 2)->nullable()->after('betaling_actief');
        });

        // Nieuwe tabel betalingen
        Schema::create('betalingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('mollie_payment_id')->unique();
            $table->decimal('bedrag', 8, 2);
            $table->integer('aantal_judokas');
            $table->string('status')->default('open'); // open, pending, paid, failed, expired, canceled
            $table->timestamp('betaald_op')->nullable();
            $table->timestamps();
        });

        // Nieuwe velden in judokas
        Schema::table('judokas', function (Blueprint $table) {
            $table->foreignId('betaling_id')->nullable()->after('synced_at')
                ->constrained('betalingen')->nullOnDelete();
            $table->timestamp('betaald_op')->nullable()->after('betaling_id');
        });
    }

    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropForeign(['betaling_id']);
            $table->dropColumn(['betaling_id', 'betaald_op']);
        });

        Schema::dropIfExists('betalingen');

        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn(['betaling_actief', 'inschrijfgeld']);
        });
    }
};
