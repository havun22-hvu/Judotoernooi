<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wimpel_judokas') || Schema::hasColumn('wimpel_judokas', 'is_nieuw')) {
            return;
        }

        Schema::table('wimpel_judokas', function (Blueprint $table) {
            $table->boolean('is_nieuw')->default(true)->after('punten_totaal');
        });
    }

    public function down(): void
    {
        Schema::table('wimpel_judokas', function (Blueprint $table) {
            $table->dropColumn('is_nieuw');
        });
    }
};
