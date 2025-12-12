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
        Schema::create('organisators', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->string('email')->unique();
            $table->string('telefoon', 20)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('laatste_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Pivot table for organisator <-> toernooi relationship
        Schema::create('organisator_toernooi', function (Blueprint $table) {
            $table->foreignId('organisator_id')->constrained('organisators')->cascadeOnDelete();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->enum('rol', ['eigenaar', 'beheerder'])->default('eigenaar');
            $table->timestamps();
            $table->primary(['organisator_id', 'toernooi_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisator_toernooi');
        Schema::dropIfExists('organisators');
    }
};
