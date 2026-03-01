<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add payment provider fields to toernooien
        Schema::table('toernooien', function (Blueprint $table) {
            $table->string('payment_provider', 20)->default('mollie')->after('mollie_organization_name');
            $table->string('stripe_account_id')->nullable()->after('payment_provider');
            $table->text('stripe_access_token')->nullable()->after('stripe_account_id');
            $table->text('stripe_refresh_token')->nullable()->after('stripe_access_token');
            $table->string('stripe_publishable_key')->nullable()->after('stripe_refresh_token');
        });

        // Add payment provider + stripe ID to betalingen
        Schema::table('betalingen', function (Blueprint $table) {
            $table->string('payment_provider', 20)->default('mollie')->after('mollie_payment_id');
            $table->string('stripe_payment_id')->nullable()->after('payment_provider');
        });

        // Add payment provider + stripe ID to toernooi_betalingen
        Schema::table('toernooi_betalingen', function (Blueprint $table) {
            $table->string('payment_provider', 20)->default('mollie')->after('mollie_payment_id');
            $table->string('stripe_payment_id')->nullable()->after('payment_provider');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn([
                'payment_provider',
                'stripe_account_id',
                'stripe_access_token',
                'stripe_refresh_token',
                'stripe_publishable_key',
            ]);
        });

        Schema::table('betalingen', function (Blueprint $table) {
            $table->dropColumn(['payment_provider', 'stripe_payment_id']);
        });

        Schema::table('toernooi_betalingen', function (Blueprint $table) {
            $table->dropColumn(['payment_provider', 'stripe_payment_id']);
        });
    }
};
