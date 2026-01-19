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
        Schema::table('poules', function (Blueprint $table) {
            $table->string('gewichtsklasse', 50)->change();
        });

        Schema::table('judokas', function (Blueprint $table) {
            $table->string('gewichtsklasse', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->string('gewichtsklasse', 10)->change();
        });

        Schema::table('judokas', function (Blueprint $table) {
            $table->string('gewichtsklasse', 10)->change();
        });
    }
};
