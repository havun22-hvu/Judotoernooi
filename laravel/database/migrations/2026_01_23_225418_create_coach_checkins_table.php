<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coach_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_kaart_id')->constrained('coach_kaarten')->cascadeOnDelete();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->string('naam');
            $table->string('club_naam');
            $table->string('foto')->nullable();
            $table->enum('actie', ['in', 'uit', 'uit_geforceerd']);
            $table->string('geforceerd_door')->nullable();
            $table->timestamps();

            $table->index(['toernooi_id', 'created_at']);
            $table->index(['coach_kaart_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_checkins');
    }
};
