<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->date('inschrijving_deadline')->nullable()->after('datum');
            $table->unsignedInteger('max_judokas')->nullable()->after('inschrijving_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn(['inschrijving_deadline', 'max_judokas']);
        });
    }
};
