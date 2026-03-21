<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wedstrijden', function (Blueprint $table) {
            $table->unsignedSmallInteger('volgorde')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('wedstrijden', function (Blueprint $table) {
            $table->unsignedTinyInteger('volgorde')->default(0)->change();
        });
    }
};
