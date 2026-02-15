<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->boolean('danpunten_actief')->default(false)->after('coach_incheck_actief');
        });

        Schema::table('judokas', function (Blueprint $table) {
            $table->string('jbn_lidnummer', 20)->nullable()->after('band');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('danpunten_actief');
        });

        Schema::table('judokas', function (Blueprint $table) {
            $table->dropColumn('jbn_lidnummer');
        });
    }
};
