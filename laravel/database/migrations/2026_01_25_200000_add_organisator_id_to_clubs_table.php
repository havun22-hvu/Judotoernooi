<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->foreignId('organisator_id')->nullable()->after('id')->constrained('organisators')->nullOnDelete();
        });

        // Optionally: migrate existing clubs to first organisator that has toernooien with those clubs
        // This is a data migration, can be done manually or via seeder
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign(['organisator_id']);
            $table->dropColumn('organisator_id');
        });
    }
};
