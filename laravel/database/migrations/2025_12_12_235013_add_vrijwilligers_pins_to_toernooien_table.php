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
            $table->string('pin_hoofdjury', 5)->nullable()->after('wachtwoord_spreker');
            $table->string('pin_weging', 5)->nullable()->after('pin_hoofdjury');
            $table->string('pin_mat', 5)->nullable()->after('pin_weging');
            $table->string('pin_spreker', 5)->nullable()->after('pin_mat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn(['pin_hoofdjury', 'pin_weging', 'pin_mat', 'pin_spreker']);
        });
    }
};
