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
            // 2 = twee bronzen medailles (beide verliezers halve finale)
            // 1 = een bronzen medaille (kleine finale in B-groep)
            $table->tinyInteger('aantal_brons')->default(2)->after('eliminatie_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('aantal_brons');
        });
    }
};
