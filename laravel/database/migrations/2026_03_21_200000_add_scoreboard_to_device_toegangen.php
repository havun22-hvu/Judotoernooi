<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add scoreboard support to device_toegangen table.
     * - api_token: Bearer token for stateless API auth (Android app)
     * - rol enum: add 'scoreboard' and 'scoreboard-display' options
     */
    public function up(): void
    {
        // Add api_token column
        Schema::table('device_toegangen', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->unique()->after('device_token');
        });

        // Expand rol enum to include scoreboard roles
        // SQLite doesn't enforce enum, MySQL does
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE device_toegangen MODIFY COLUMN rol ENUM('hoofdjury', 'mat', 'weging', 'spreker', 'dojo', 'scoreboard')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_toegangen', function (Blueprint $table) {
            $table->dropColumn('api_token');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE device_toegangen MODIFY COLUMN rol ENUM('hoofdjury', 'mat', 'weging', 'spreker', 'dojo')");
        }
    }
};
