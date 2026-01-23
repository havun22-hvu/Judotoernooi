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
            // uit = alleen bekijken, mutaties = wijzigen bestaande, volledig = ook nieuwe inschrijvingen
            $table->string('portaal_modus', 20)->default('uit')->after('betaling_actief');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('portaal_modus');
        });
    }
};
