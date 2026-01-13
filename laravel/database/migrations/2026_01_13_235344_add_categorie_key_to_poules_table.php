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
            // Key for grouping in block distribution (e.g., "m_variabel", "beginners")
            $table->string('categorie_key', 50)->nullable()->after('gewichtsklasse');
            $table->index('categorie_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->dropIndex(['categorie_key']);
            $table->dropColumn('categorie_key');
        });
    }
};
