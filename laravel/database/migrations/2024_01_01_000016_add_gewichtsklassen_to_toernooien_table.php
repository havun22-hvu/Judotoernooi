<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->json('gewichtsklassen')->nullable()->after('inschrijving_gesloten_op');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('gewichtsklassen');
        });
    }
};
