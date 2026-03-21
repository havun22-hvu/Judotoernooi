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
        Schema::table('matten', function (Blueprint $table) {
            $table->foreignId('actieve_wedstrijd_id')->nullable()->constrained('wedstrijden')->nullOnDelete();
            $table->foreignId('volgende_wedstrijd_id')->nullable()->constrained('wedstrijden')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matten', function (Blueprint $table) {
            $table->dropForeign(['actieve_wedstrijd_id']);
            $table->dropForeign(['volgende_wedstrijd_id']);
            $table->dropColumn(['actieve_wedstrijd_id', 'volgende_wedstrijd_id']);
        });
    }
};
