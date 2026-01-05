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
        Schema::table('toernooien', function (Blueprint $table) {
            $table->decimal('max_kg_verschil', 3, 1)->default(3.0)->after('judoka_code_volgorde');
            $table->unsignedTinyInteger('max_leeftijd_verschil')->default(2)->after('max_kg_verschil');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn(['max_kg_verschil', 'max_leeftijd_verschil']);
        });
    }
};
