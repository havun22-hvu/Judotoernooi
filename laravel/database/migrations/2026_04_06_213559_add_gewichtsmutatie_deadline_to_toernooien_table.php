<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->date('gewichtsmutatie_deadline')->nullable()->after('inschrijving_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('gewichtsmutatie_deadline');
        });
    }
};
