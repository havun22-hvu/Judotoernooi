<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wimpel_punten_log', function (Blueprint $table) {
            $table->unsignedBigInteger('poule_id')->nullable()->after('toernooi_id');
            $table->foreign('poule_id')->references('id')->on('poules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wimpel_punten_log', function (Blueprint $table) {
            $table->dropForeign(['poule_id']);
            $table->dropColumn('poule_id');
        });
    }
};
