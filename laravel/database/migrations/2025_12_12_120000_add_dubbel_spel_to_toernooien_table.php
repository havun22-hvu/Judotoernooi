<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->boolean('dubbel_bij_2_judokas')->default(true)->after('clubspreiding');
            $table->boolean('dubbel_bij_3_judokas')->default(true)->after('dubbel_bij_2_judokas');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn(['dubbel_bij_2_judokas', 'dubbel_bij_3_judokas']);
        });
    }
};
