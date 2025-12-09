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
        Schema::table('poules', function (Blueprint $table) {
            // Type: voorronde (default) or kruisfinale
            $table->string('type', 20)->default('voorronde')->after('titel');
            // For kruisfinale: how many places qualify (from kruisfinales_aantal)
            $table->unsignedTinyInteger('kruisfinale_plaatsen')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->dropColumn(['type', 'kruisfinale_plaatsen']);
        });
    }
};
