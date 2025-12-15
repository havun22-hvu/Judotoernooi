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
        Schema::table('poules', function (Blueprint $table) {
            // Timestamp when category was sent to zaaloverzicht from wedstrijddag
            // null = not sent yet (grey chip in zaaloverzicht)
            // set = sent (white chip, ready for activation)
            $table->timestamp('doorgestuurd_op')->nullable()->after('blok_vast');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poules', function (Blueprint $table) {
            $table->dropColumn('doorgestuurd_op');
        });
    }
};
