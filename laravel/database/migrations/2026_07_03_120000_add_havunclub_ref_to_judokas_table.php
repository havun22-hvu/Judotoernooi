<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HavunClub can fill an invited school portal via the portal-fill API. Storing
 * the HavunClub judoka id on the tournament Judoka enables deterministic,
 * fuzzy-match-free idempotency on repeated pushes (mirrors
 * stam_judokas.havunclub_ref). Nullable + additive: solo judokas are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->string('havunclub_ref')->nullable()->index()->after('stam_judoka_id');
        });
    }

    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropIndex(['havunclub_ref']);
            $table->dropColumn('havunclub_ref');
        });
    }
};
