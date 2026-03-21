<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for club-toernooi relationship with portal credentials
        Schema::create('club_toernooi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->onDelete('cascade');
            $table->foreignId('toernooi_id')->constrained('toernooien')->onDelete('cascade');
            $table->string('portal_code', 12)->nullable();
            $table->string('pincode', 5)->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'toernooi_id']);
            $table->unique(['toernooi_id', 'portal_code']);
        });

        // Migrate existing data: for each club that has judokas in a toernooi,
        // create a pivot record with a new portal_code
        $this->migrateExistingData();
    }

    private function migrateExistingData(): void
    {
        // Find all unique club-toernooi combinations from judokas
        $combinations = DB::table('judokas')
            ->select('club_id', 'toernooi_id')
            ->whereNotNull('club_id')
            ->whereNotNull('toernooi_id')
            ->distinct()
            ->get();

        foreach ($combinations as $combo) {
            // Get the club's original portal_code and pincode (for reference/migration)
            $club = DB::table('clubs')->where('id', $combo->club_id)->first();

            if (!$club) {
                continue;
            }

            // Generate new unique portal_code for this toernooi
            $portalCode = $this->generateUniquePortalCode($combo->toernooi_id);

            // Generate new pincode
            $pincode = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

            DB::table('club_toernooi')->insert([
                'club_id' => $combo->club_id,
                'toernooi_id' => $combo->toernooi_id,
                'portal_code' => $portalCode,
                'pincode' => $pincode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function generateUniquePortalCode(int $toernooiId): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

        do {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }

            // Check uniqueness within this toernooi
            $exists = DB::table('club_toernooi')
                ->where('toernooi_id', $toernooiId)
                ->where('portal_code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    public function down(): void
    {
        Schema::dropIfExists('club_toernooi');
    }
};
