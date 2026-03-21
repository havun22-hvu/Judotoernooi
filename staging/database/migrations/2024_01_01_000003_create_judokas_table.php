<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judokas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();

            // Persoonlijke gegevens
            $table->string('naam');
            $table->string('voornaam')->nullable();
            $table->string('achternaam')->nullable();
            $table->year('geboortejaar');
            $table->string('geslacht', 1); // M of V
            $table->string('band', 20); // wit, geel, oranje, groen, blauw, bruin, zwart
            $table->decimal('gewicht', 4, 1)->nullable(); // Opgegeven gewicht

            // Berekende classificatie
            $table->string('leeftijdsklasse', 20);
            $table->string('gewichtsklasse', 10); // bijv. "-36" of "+70"
            $table->string('judoka_code', 20)->nullable(); // Unieke code voor poule-indeling

            // Toernooidag
            $table->string('aanwezigheid', 20)->default('onbekend');
            $table->decimal('gewicht_gewogen', 4, 1)->nullable();
            $table->string('opmerking')->nullable();

            // QR code voor check-in
            $table->string('qr_code', 50)->nullable()->unique();

            $table->timestamps();

            $table->index(['toernooi_id', 'leeftijdsklasse', 'gewichtsklasse']);
            $table->index(['toernooi_id', 'judoka_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judokas');
    }
};
