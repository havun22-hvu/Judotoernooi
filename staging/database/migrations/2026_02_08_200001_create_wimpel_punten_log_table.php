<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wimpel_punten_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wimpel_judoka_id')->constrained('wimpel_judokas')->cascadeOnDelete();
            $table->foreignId('toernooi_id')->nullable()->constrained('toernooien')->nullOnDelete();
            $table->unsignedBigInteger('poule_id')->nullable();
            $table->integer('punten');
            $table->string('type', 20); // 'automatisch' or 'handmatig'
            $table->string('notitie')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wimpel_punten_log');
    }
};
