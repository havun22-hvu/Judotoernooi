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
        Schema::table('judokas', function (Blueprint $table) {
            $table->boolean('is_onvolledig')->default(false)->after('opmerking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropColumn('is_onvolledig');
        });
    }
};
