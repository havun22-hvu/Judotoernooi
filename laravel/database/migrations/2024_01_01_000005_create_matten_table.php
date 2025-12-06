<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->unsignedTinyInteger('nummer');
            $table->string('naam')->nullable(); // bijv. "Mat 1 - Centrale mat"
            $table->string('kleur', 20)->nullable(); // Voor visuele identificatie
            $table->timestamps();

            $table->unique(['toernooi_id', 'nummer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matten');
    }
};
