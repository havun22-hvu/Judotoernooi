<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropUnique('judokas_unique_per_toernooi');
        });
    }

    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->unique(['toernooi_id', 'naam', 'geboortejaar'], 'judokas_unique_per_toernooi');
        });
    }
};
