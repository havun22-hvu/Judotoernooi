<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->unsignedBigInteger('overpouled_van_poule_id')->nullable()->after('opmerking');
            $table->foreign('overpouled_van_poule_id')->references('id')->on('poules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropForeign(['overpouled_van_poule_id']);
            $table->dropColumn('overpouled_van_poule_id');
        });
    }
};
