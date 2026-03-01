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
        Schema::table('toernooi_betalingen', function (Blueprint $table) {
            $table->string('factuurnummer', 20)->nullable()->unique()->after('betaald_op');
        });
    }

    public function down(): void
    {
        Schema::table('toernooi_betalingen', function (Blueprint $table) {
            $table->dropColumn('factuurnummer');
        });
    }
};
