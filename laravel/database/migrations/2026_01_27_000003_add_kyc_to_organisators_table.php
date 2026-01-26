<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // KYC / Facturatiegegevens - add each column separately
        $columns = [
            'organisatie_naam' => fn(Blueprint $t) => $t->string('organisatie_naam')->nullable(),
            'kvk_nummer' => fn(Blueprint $t) => $t->string('kvk_nummer')->nullable(),
            'btw_nummer' => fn(Blueprint $t) => $t->string('btw_nummer')->nullable(),
            'straat' => fn(Blueprint $t) => $t->string('straat')->nullable(),
            'postcode' => fn(Blueprint $t) => $t->string('postcode')->nullable(),
            'plaats' => fn(Blueprint $t) => $t->string('plaats')->nullable(),
            'land' => fn(Blueprint $t) => $t->string('land')->default('Nederland'),
            'contactpersoon' => fn(Blueprint $t) => $t->string('contactpersoon')->nullable(),
            // telefoon already exists in original table
            'factuur_email' => fn(Blueprint $t) => $t->string('factuur_email')->nullable(),
            'website' => fn(Blueprint $t) => $t->string('website')->nullable(),
            'kyc_compleet' => fn(Blueprint $t) => $t->boolean('kyc_compleet')->default(false),
            'kyc_ingevuld_op' => fn(Blueprint $t) => $t->timestamp('kyc_ingevuld_op')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (!Schema::hasColumn('organisators', $column)) {
                Schema::table('organisators', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        $columns = [
            'organisatie_naam',
            'kvk_nummer',
            'btw_nummer',
            'straat',
            'postcode',
            'plaats',
            'land',
            'contactpersoon',
            // telefoon is kept (was in original table)
            'factuur_email',
            'website',
            'kyc_compleet',
            'kyc_ingevuld_op',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('organisators', $column)) {
                Schema::table('organisators', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
