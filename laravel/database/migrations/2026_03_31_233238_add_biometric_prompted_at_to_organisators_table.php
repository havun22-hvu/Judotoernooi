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
            $table->timestamp('biometric_prompted_at')->nullable()->after('laatste_login');
        });
    }

    public function down(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->dropColumn('biometric_prompted_at');
        });
    }
};
