<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vrijwilligers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisator_id')->constrained('organisators')->onDelete('cascade');
            $table->string('voornaam');
            $table->string('telefoonnummer')->nullable();
            $table->enum('functie', ['mat', 'weging', 'spreker', 'dojo', 'hoofdjury']);
            $table->timestamps();

            $table->index(['organisator_id', 'functie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vrijwilligers');
    }
};
