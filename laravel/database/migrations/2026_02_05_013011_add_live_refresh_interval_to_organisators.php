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
        Schema::table('organisators', function (Blueprint $table) {
            $table->unsignedSmallInteger('live_refresh_interval')->nullable()->after('is_premium');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->dropColumn('live_refresh_interval');
        });
    }
};
