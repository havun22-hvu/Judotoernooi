<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->foreignId('blok_id')->nullable()->constrained('blokken')->nullOnDelete();
            $table->foreignId('mat_id')->nullable()->constrained('matten')->nullOnDelete();

            $table->unsignedInteger('nummer'); // Poule nummer (1, 2, 3...)
            $table->string('titel'); // bijv. "Mini's -20 kg Poule 1"
            $table->string('leeftijdsklasse', 20);
            $table->string('gewichtsklasse', 10);

            // Statistieken (cached voor performance)
            $table->unsignedTinyInteger('aantal_judokas')->default(0);
            $table->unsignedSmallInteger('aantal_wedstrijden')->default(0);

            $table->timestamps();

            $table->unique(['toernooi_id', 'nummer']);
            $table->index(['toernooi_id', 'blok_id', 'mat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poules');
    }
};
