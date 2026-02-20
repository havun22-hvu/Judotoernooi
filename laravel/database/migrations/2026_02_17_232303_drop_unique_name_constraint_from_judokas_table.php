<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safe drop: index may not exist on fresh SQLite databases
        try {
            Schema::table('judokas', function (Blueprint $table) {
                $table->dropUnique('judokas_unique_per_toernooi');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, nothing to drop
        }
    }

    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->unique(['toernooi_id', 'naam', 'geboortejaar'], 'judokas_unique_per_toernooi');
        });
    }
};
