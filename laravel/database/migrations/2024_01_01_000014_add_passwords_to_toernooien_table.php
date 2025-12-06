<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            // Organisatie wachtwoorden
            $table->string('wachtwoord_admin')->nullable()->after('blokken_verdeeld_op');
            $table->string('wachtwoord_jury')->nullable()->after('wachtwoord_admin');
            $table->string('wachtwoord_weging')->nullable()->after('wachtwoord_jury');
            $table->string('wachtwoord_mat')->nullable()->after('wachtwoord_weging');
            $table->string('wachtwoord_spreker')->nullable()->after('wachtwoord_mat');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn([
                'wachtwoord_admin',
                'wachtwoord_jury',
                'wachtwoord_weging',
                'wachtwoord_mat',
                'wachtwoord_spreker',
            ]);
        });
    }
};
