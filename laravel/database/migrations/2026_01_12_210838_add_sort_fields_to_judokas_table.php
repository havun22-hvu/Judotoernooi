<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add sort fields to replace complex judoka_code string.
     * These fields enable simple numeric sorting:
     * ORDER BY sort_categorie, sort_gewicht, sort_band
     */
    public function up(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            // Categorie volgorde uit preset config (0, 1, 2, ...)
            $table->unsignedSmallInteger('sort_categorie')->default(0)->after('judoka_code');

            // Gewicht in grammen (30500 = 30.5kg) voor nauwkeurige sortering
            $table->unsignedInteger('sort_gewicht')->default(0)->after('sort_categorie');

            // Band niveau (1=wit, 2=geel, 3=oranje, 4=groen, 5=blauw, 6=bruin, 7=zwart)
            $table->unsignedTinyInteger('sort_band')->default(0)->after('sort_gewicht');

            // Config key voor interne lookup (bijv. 'u11_h', 'minis')
            $table->string('categorie_key', 50)->nullable()->after('sort_band');

            // Composite index voor efficiënte sortering
            $table->index(['toernooi_id', 'sort_categorie', 'sort_gewicht', 'sort_band'], 'judokas_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropIndex('judokas_sort_index');
            $table->dropColumn(['sort_categorie', 'sort_gewicht', 'sort_band', 'categorie_key']);
        });
    }
};
