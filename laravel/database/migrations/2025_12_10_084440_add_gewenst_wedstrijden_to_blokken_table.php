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
        Schema::table('blokken', function (Blueprint $table) {
            $table->integer('gewenst_wedstrijden')->nullable()->after('nummer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blokken', function (Blueprint $table) {
            $table->dropColumn('gewenst_wedstrijden');
        });
    }
};
