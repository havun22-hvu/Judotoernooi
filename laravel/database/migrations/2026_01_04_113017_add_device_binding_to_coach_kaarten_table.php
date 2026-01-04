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
        Schema::table('coach_kaarten', function (Blueprint $table) {
            $table->string('device_token', 64)->nullable()->after('gescand_op');
            $table->string('device_info', 255)->nullable()->after('device_token');
            $table->timestamp('gebonden_op')->nullable()->after('device_info');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coach_kaarten', function (Blueprint $table) {
            $table->dropColumn(['device_token', 'device_info', 'gebonden_op']);
        });
    }
};
