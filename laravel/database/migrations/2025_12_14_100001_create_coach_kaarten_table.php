<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_kaarten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->onDelete('cascade');
            $table->foreignId('club_id')->constrained()->onDelete('cascade');
            $table->string('naam')->nullable(); // Optioneel, kan later ingevuld worden
            $table->string('qr_code', 32)->unique(); // Unieke code voor QR
            $table->boolean('is_gescand')->default(false); // Toegang geregistreerd
            $table->timestamp('gescand_op')->nullable();
            $table->timestamps();

            $table->index(['toernooi_id', 'club_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_kaarten');
    }
};
