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
        Schema::create('coach_kaart_wisselingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_kaart_id')->constrained('coach_kaarten')->onDelete('cascade');
            $table->string('naam');
            $table->string('foto')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamp('geactiveerd_op');
            $table->timestamp('overgedragen_op')->nullable(); // NULL = huidige coach
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_kaart_wisselingen');
    }
};
