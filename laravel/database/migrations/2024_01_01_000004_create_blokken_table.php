<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blokken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->unsignedTinyInteger('nummer');
            $table->time('starttijd')->nullable();
            $table->time('eindtijd')->nullable();
            $table->boolean('weging_gesloten')->default(false);
            $table->timestamp('weging_gesloten_op')->nullable();
            $table->timestamps();

            $table->unique(['toernooi_id', 'nummer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blokken');
    }
};
