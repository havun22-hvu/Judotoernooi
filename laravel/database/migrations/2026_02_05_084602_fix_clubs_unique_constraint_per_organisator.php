<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix: clubs.naam should be unique per organisator, not globally
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Drop the old global unique constraint on naam
            $table->dropUnique('clubs_naam_unique');

            // Add new composite unique constraint: naam per organisator
            $table->unique(['organisator_id', 'naam'], 'clubs_naam_per_organisator_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropUnique('clubs_naam_per_organisator_unique');
            $table->unique('naam', 'clubs_naam_unique');
        });
    }
};
