<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add payment_processed_at for webhook idempotency.
 *
 * Webhooks (Mollie/Stripe) can be delivered multiple times. We use this
 * timestamp as an idempotency marker so the same successful payment is
 * never finalized twice (prevents double invoicing / double-marking).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('betalingen', function (Blueprint $table) {
            $table->timestamp('payment_processed_at')->nullable()->after('betaald_op');
        });

        Schema::table('toernooi_betalingen', function (Blueprint $table) {
            $table->timestamp('payment_processed_at')->nullable()->after('betaald_op');
        });
    }

    public function down(): void
    {
        Schema::table('betalingen', function (Blueprint $table) {
            $table->dropColumn('payment_processed_at');
        });

        Schema::table('toernooi_betalingen', function (Blueprint $table) {
            $table->dropColumn('payment_processed_at');
        });
    }
};
