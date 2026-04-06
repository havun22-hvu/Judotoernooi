<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_koppelingen', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();
            $table->foreignId('toernooi_id')->nullable()->constrained('toernooien')->nullOnDelete();
            $table->unsignedInteger('mat_nummer')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_koppelingen');
    }
};
