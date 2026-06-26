<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External reference for HavunClub judoka-sync idempotency.
 *
 * Lets a repeated POST /api/judokas resolve to the same StamJudoka even when
 * HavunClub did not persist the returned id (defensive idempotency key).
 * Nullable + additive: existing rows and the solo flow are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stam_judokas', function (Blueprint $table) {
            $table->string('havunclub_ref')->nullable()->after('organisator_id');
            $table->index(['organisator_id', 'havunclub_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('stam_judokas', function (Blueprint $table) {
            $table->dropIndex(['organisator_id', 'havunclub_ref']);
            $table->dropColumn('havunclub_ref');
        });
    }
};
