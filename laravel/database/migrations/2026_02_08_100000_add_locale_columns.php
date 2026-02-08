<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('live_refresh_interval');
        });

        Schema::table('toernooien', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('hotspot_ip');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
