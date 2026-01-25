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
            // Link naar originele poule voor barrage
            $table->foreignId('barrage_van_poule_id')
                ->nullable()
                ->after('categorie_key')
                ->constrained('poules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barrage_van_poule_id');
        });
    }
};
