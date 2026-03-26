<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->string('toernooi_type', 10)->default('intern')->after('naam');
        });

        // Existing tournaments default to 'open' (backwards compatible)
        DB::table('toernooien')->update(['toernooi_type' => 'open']);
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('toernooi_type');
        });
    }
};
