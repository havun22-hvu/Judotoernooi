<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wegingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judoka_id')->constrained('judokas')->cascadeOnDelete();
            $table->decimal('gewicht', 4, 1);
            $table->boolean('binnen_klasse')->default(true);
            $table->string('alternatieve_poule')->nullable();
            $table->string('opmerking')->nullable();
            $table->string('geregistreerd_door')->nullable();
            $table->timestamps();

            $table->index(['judoka_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wegingen');
    }
};
