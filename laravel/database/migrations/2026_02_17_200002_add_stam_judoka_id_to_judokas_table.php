<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->foreignId('stam_judoka_id')->nullable()->after('club_id')
                ->constrained('stam_judokas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stam_judoka_id');
        });
    }
};
