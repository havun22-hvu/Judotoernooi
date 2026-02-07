<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->string('actie', 50);
            $table->string('model_type', 50)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('beschrijving');
            $table->json('properties')->nullable();
            $table->string('actor_type', 30)->default('systeem');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_naam', 100)->default('Systeem');
            $table->string('ip_adres', 45)->nullable();
            $table->string('interface', 30)->nullable();
            $table->timestamps();

            $table->index(['toernooi_id', 'created_at']);
            $table->index(['toernooi_id', 'actie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
