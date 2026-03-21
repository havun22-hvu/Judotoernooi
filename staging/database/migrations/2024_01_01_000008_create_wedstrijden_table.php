<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedstrijden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poule_id')->constrained('poules')->cascadeOnDelete();
            $table->foreignId('judoka_wit_id')->constrained('judokas')->cascadeOnDelete();
            $table->foreignId('judoka_blauw_id')->constrained('judokas')->cascadeOnDelete();

            $table->unsignedTinyInteger('volgorde'); // Wedstrijdvolgorde binnen poule

            // Uitslag
            $table->foreignId('winnaar_id')->nullable()->constrained('judokas')->nullOnDelete();
            $table->string('score_wit', 20)->nullable(); // bijv. "Ippon", "Waza-ari"
            $table->string('score_blauw', 20)->nullable();
            $table->string('uitslag_type', 20)->nullable(); // ippon, waza-ari, beslissing, etc.

            $table->boolean('is_gespeeld')->default(false);
            $table->timestamp('gespeeld_op')->nullable();

            $table->timestamps();

            $table->index(['poule_id', 'volgorde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wedstrijden');
    }
};
