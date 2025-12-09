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
        Schema::table('toernooien', function (Blueprint $table) {
            // JSON array with preference order, e.g. [5, 4, 6, 3]
            // First = most preferred, last = least preferred
            $table->json('poule_grootte_voorkeur')->nullable()->after('max_judokas_poule');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('poule_grootte_voorkeur');
        });
    }
};
