<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->boolean('wimpel_abo_actief')->default(false)->after('locale');
            $table->date('wimpel_abo_start')->nullable()->after('wimpel_abo_actief');
            $table->date('wimpel_abo_einde')->nullable()->after('wimpel_abo_start');
            $table->decimal('wimpel_abo_prijs', 8, 2)->nullable()->after('wimpel_abo_einde');
            $table->text('wimpel_abo_notities')->nullable()->after('wimpel_abo_prijs');
        });
    }

    public function down(): void
    {
        Schema::table('organisators', function (Blueprint $table) {
            $table->dropColumn([
                'wimpel_abo_actief',
                'wimpel_abo_start',
                'wimpel_abo_einde',
                'wimpel_abo_prijs',
                'wimpel_abo_notities',
            ]);
        });
    }
};
