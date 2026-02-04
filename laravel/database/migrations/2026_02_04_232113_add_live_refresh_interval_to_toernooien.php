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
            // Live refresh interval in seconds (null = adaptive/auto)
            // Options: null (auto), 5, 10, 15, 30, 60
            $table->unsignedSmallInteger('live_refresh_interval')->nullable()->after('hotspot_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('live_refresh_interval');
        });
    }
};
