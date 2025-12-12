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
        Schema::table('toernooien', function (Blueprint $table) {
            $table->string('code_hoofdjury', 12)->nullable()->unique();
            $table->string('code_weging', 12)->nullable()->unique();
            $table->string('code_mat', 12)->nullable()->unique();
            $table->string('code_spreker', 12)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn(['code_hoofdjury', 'code_weging', 'code_mat', 'code_spreker']);
        });
    }
};
