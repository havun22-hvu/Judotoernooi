<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_aanmeldingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->string('club_naam');
            $table->string('contact_naam')->nullable();
            $table->string('email')->nullable();
            $table->string('telefoon', 20)->nullable();
            $table->string('status', 15)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_aanmeldingen');
    }
};
