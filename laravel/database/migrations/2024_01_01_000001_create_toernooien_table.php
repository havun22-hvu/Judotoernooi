<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toernooien', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->string('organisatie')->nullable();
            $table->date('datum');
            $table->string('locatie')->nullable();

            // Configuratie
            $table->unsignedTinyInteger('aantal_matten')->default(7);
            $table->unsignedTinyInteger('aantal_blokken')->default(6);
            $table->unsignedTinyInteger('min_judokas_poule')->default(3);
            $table->unsignedTinyInteger('optimal_judokas_poule')->default(5);
            $table->unsignedTinyInteger('max_judokas_poule')->default(6);
            $table->decimal('gewicht_tolerantie', 3, 1)->default(0.5);

            // Status
            $table->boolean('is_actief')->default(true);
            $table->timestamp('poules_gegenereerd_op')->nullable();
            $table->timestamp('blokken_verdeeld_op')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toernooien');
    }
};
