<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poule_judoka', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poule_id')->constrained('poules')->cascadeOnDelete();
            $table->foreignId('judoka_id')->constrained('judokas')->cascadeOnDelete();
            $table->unsignedTinyInteger('positie')->nullable(); // Volgorde binnen poule

            // Resultaten
            $table->unsignedTinyInteger('punten')->default(0);
            $table->unsignedTinyInteger('gewonnen')->default(0);
            $table->unsignedTinyInteger('verloren')->default(0);
            $table->unsignedTinyInteger('gelijk')->default(0);
            $table->unsignedTinyInteger('eindpositie')->nullable();

            $table->timestamps();

            $table->unique(['poule_id', 'judoka_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poule_judoka');
    }
};
