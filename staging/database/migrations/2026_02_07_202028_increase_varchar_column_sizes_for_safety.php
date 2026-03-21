<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Increase varchar column sizes to prevent truncation errors.
 *
 * Root cause: verplaatsJudoka() wrote poule gewichtsklasse range (e.g. "23.5-24.5kg")
 * to judokas.gewichtsklasse (varchar(10)) causing SQL truncation and orphaned judoka.
 *
 * This migration increases all varchar columns that could receive dynamic/concatenated
 * values to safe sizes with margin.
 */
return new class extends Migration
{
    public function up(): void
    {
        // judokas: leeftijdsklasse 20->50, judoka_code 20->50, band 20->30
        Schema::table('judokas', function (Blueprint $table) {
            $table->string('leeftijdsklasse', 50)->nullable()->change();
            $table->string('judoka_code', 50)->nullable()->change();
            $table->string('band', 30)->nullable()->change();
        });

        // poules: leeftijdsklasse 20->50, type 20->30
        Schema::table('poules', function (Blueprint $table) {
            $table->string('leeftijdsklasse', 50)->change();
            $table->string('type', 30)->change();
        });

        // clubs: afkorting 10->30 (was already at limit with "Toradoshi")
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('afkorting', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->string('leeftijdsklasse', 20)->nullable()->change();
            $table->string('judoka_code', 20)->nullable()->change();
            $table->string('band', 20)->nullable()->change();
        });

        Schema::table('poules', function (Blueprint $table) {
            $table->string('leeftijdsklasse', 20)->change();
            $table->string('type', 20)->change();
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->string('afkorting', 10)->nullable()->change();
        });
    }
};
