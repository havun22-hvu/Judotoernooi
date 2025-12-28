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
            // 'dubbel' = volledige dubbel eliminatie (alle verliezers herkansen)
            // 'ijf' = officieel IJF systeem (beperkte repechage)
            $table->string('eliminatie_type', 20)->default('dubbel')->after('eliminatie_gewichtsklassen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('eliminatie_type');
        });
    }
};
