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
        Schema::table('gewichtsklassen_presets', function (Blueprint $table) {
            // Drop old foreign key and column
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('gewichtsklassen_presets', function (Blueprint $table) {
            // Add new column with correct foreign key
            $table->foreignId('organisator_id')->after('id')->constrained('organisators')->onDelete('cascade');
        });

        // Recreate unique constraint
        Schema::table('gewichtsklassen_presets', function (Blueprint $table) {
            $table->unique(['organisator_id', 'naam']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gewichtsklassen_presets', function (Blueprint $table) {
            $table->dropForeign(['organisator_id']);
            $table->dropUnique(['organisator_id', 'naam']);
            $table->dropColumn('organisator_id');
        });

        Schema::table('gewichtsklassen_presets', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            $table->unique(['user_id', 'naam']);
        });
    }
};
