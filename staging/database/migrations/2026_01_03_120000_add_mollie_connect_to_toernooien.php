<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            // Mollie mode: 'connect' (organisator's Mollie) of 'platform' (via JudoToernooi)
            $table->string('mollie_mode')->default('platform')->after('inschrijfgeld');

            // Platform mode toeslag (bijv. 0.50 of percentage)
            $table->decimal('platform_toeslag', 8, 2)->default(0.50)->after('mollie_mode');
            $table->boolean('platform_toeslag_percentage')->default(false)->after('platform_toeslag');

            // Mollie Connect OAuth tokens (voor organisator's eigen Mollie)
            $table->string('mollie_account_id')->nullable()->after('platform_toeslag_percentage');
            $table->text('mollie_access_token')->nullable()->after('mollie_account_id');
            $table->text('mollie_refresh_token')->nullable()->after('mollie_access_token');
            $table->timestamp('mollie_token_expires_at')->nullable()->after('mollie_refresh_token');
            $table->boolean('mollie_onboarded')->default(false)->after('mollie_token_expires_at');
            $table->string('mollie_organization_name')->nullable()->after('mollie_onboarded');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn([
                'mollie_mode',
                'platform_toeslag',
                'platform_toeslag_percentage',
                'mollie_account_id',
                'mollie_access_token',
                'mollie_refresh_token',
                'mollie_token_expires_at',
                'mollie_onboarded',
                'mollie_organization_name',
            ]);
        });
    }
};
