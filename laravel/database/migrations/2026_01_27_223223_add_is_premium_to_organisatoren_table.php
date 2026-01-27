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
        Schema::table('organisators', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false)->after('wachtwoord');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->dropColumn('is_premium');
        });
    }
};
