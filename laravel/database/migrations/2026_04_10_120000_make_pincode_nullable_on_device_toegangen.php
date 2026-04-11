<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the `pincode` column on device_toegangen nullable.
 *
 * The 4-digit PIN system on DeviceToegang has been removed for security
 * reasons (only ~13 bits of entropy, trivially brute-forceable). Device
 * binding is now handled automatically by the 12-character role code in
 * the URL (~71 bits of entropy).
 *
 * We keep the column around (nullable) instead of dropping it so that
 * existing production rows remain readable and historical audit data is
 * preserved. A future migration can drop the column entirely once all
 * clients have been updated.
 */
return new class extends Migration
{
    public function up(): void
    {
        // doctrine/dbal is required for ->change() on older Laravel versions,
        // but Laravel 11 supports ->change() natively.
        if (Schema::hasColumn('device_toegangen', 'pincode')) {
            Schema::table('device_toegangen', function (Blueprint $table) {
                $table->string('pincode', 4)->nullable()->change();
            });

            // Null out existing pincodes so they can't accidentally be used.
            DB::table('device_toegangen')->update(['pincode' => null]);
        }

        // Note: the `pincode` column on `club_toernooi` is intentionally
        // kept as-is. That is the 5-digit coach portal PIN, which is a
        // separate system used by CoachPortalController and is NOT part
        // of this cleanup.
    }

    public function down(): void
    {
        // Intentionally left as a no-op. Reverting this migration would
        // require re-generating valid PINs for all existing rows, which
        // is not something we want to do automatically. If a rollback is
        // needed, restore from backup.
    }
};
