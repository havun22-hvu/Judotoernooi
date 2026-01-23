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
            $table->timestamp('ingecheckt_op')->nullable();
        });

        Schema::table('toernooien', function (Blueprint $table) {
            $table->boolean('coach_incheck_actief')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coach_kaarten', function (Blueprint $table) {
            $table->dropColumn('ingecheckt_op');
        });

        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('coach_incheck_actief');
        });
    }
};
