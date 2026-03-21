<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * SQLite doesn't support dropping columns well, so recreate the table
     */
    public function up(): void
    {
        // Original migration is now fixed - this migration is no longer needed
        // Kept for migration history compatibility
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gewichtsklassen_presets');

        Schema::create('gewichtsklassen_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('naam', 100);
            $table->json('configuratie');
            $table->timestamps();

            $table->unique(['user_id', 'naam']);
        });
    }
};
