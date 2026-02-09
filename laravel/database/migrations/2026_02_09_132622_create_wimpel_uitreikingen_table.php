<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wimpel_uitreikingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wimpel_judoka_id')->constrained('wimpel_judokas')->cascadeOnDelete();
            $table->foreignId('wimpel_milestone_id')->constrained('wimpel_milestones')->cascadeOnDelete();
            $table->foreignId('toernooi_id')->nullable()->constrained('toernooien')->nullOnDelete();
            $table->boolean('uitgereikt')->default(false);
            $table->timestamp('uitgereikt_at')->nullable();
            $table->timestamps();

            $table->unique(['wimpel_judoka_id', 'wimpel_milestone_id'], 'wimpel_uitreik_judoka_milestone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wimpel_uitreikingen');
    }
};
