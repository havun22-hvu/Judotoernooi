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
            $table->boolean('herdenkingsportaal')->default(false)->after('is_test');
            $table->boolean('kortingsregeling')->default(false)->after('herdenkingsportaal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->dropColumn(['herdenkingsportaal', 'kortingsregeling']);
        });
    }
};
