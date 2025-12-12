<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            if (!Schema::hasColumn('toernooien', 'dubbel_bij_4_judokas')) {
                $table->boolean('dubbel_bij_4_judokas')->default(false)->after('dubbel_bij_3_judokas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('dubbel_bij_4_judokas');
        });
    }
};
