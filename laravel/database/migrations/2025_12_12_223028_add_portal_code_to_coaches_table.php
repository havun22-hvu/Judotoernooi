<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->string('portal_code', 12)->nullable()->after('uuid');
            $table->index('portal_code');
        });

        // Generate portal codes for existing coaches (shared per club+toernooi)
        $coaches = DB::table('coaches')->get();
        $codes = [];

        foreach ($coaches as $coach) {
            $key = $coach->club_id . '_' . $coach->toernooi_id;
            if (!isset($codes[$key])) {
                $codes[$key] = $this->generateCode();
            }
            DB::table('coaches')
                ->where('id', $coach->id)
                ->update(['portal_code' => $codes[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropIndex(['portal_code']);
            $table->dropColumn('portal_code');
        });
    }

    private function generateCode(): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
};
