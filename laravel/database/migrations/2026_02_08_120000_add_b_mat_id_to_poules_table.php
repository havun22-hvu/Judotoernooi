<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->unsignedBigInteger('b_mat_id')->nullable()->after('mat_id');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('b_mat_id')->references('id')->on('matten')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['b_mat_id']);
            }
            $table->dropColumn('b_mat_id');
        });
    }
};
