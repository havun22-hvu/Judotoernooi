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
            $table->foreignId('huidige_wedstrijd_id')->nullable()->after('afgeroepen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->dropColumn('huidige_wedstrijd_id');
        });
    }
};
