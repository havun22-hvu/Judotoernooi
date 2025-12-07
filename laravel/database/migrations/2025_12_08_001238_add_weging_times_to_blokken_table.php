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
            $table->time('weging_start')->nullable()->after('nummer');
            $table->time('weging_einde')->nullable()->after('weging_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blokken', function (Blueprint $table) {
            $table->dropColumn(['weging_start', 'weging_einde']);
        });
    }
};
