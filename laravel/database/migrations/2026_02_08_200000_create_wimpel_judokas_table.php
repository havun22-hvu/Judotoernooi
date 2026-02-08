<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wimpel_judokas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisator_id')->constrained('organisators')->cascadeOnDelete();
            $table->string('naam');
            $table->unsignedSmallInteger('geboortejaar');
            $table->unsignedInteger('punten_totaal')->default(0);
            $table->timestamps();

            $table->unique(['organisator_id', 'naam', 'geboortejaar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wimpel_judokas');
    }
};
