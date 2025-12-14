<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_kaarten', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('naam'); // Path to uploaded photo
            $table->string('activatie_token', 64)->nullable()->after('foto'); // For first-time activation
            $table->boolean('is_geactiveerd')->default(false)->after('activatie_token');
            $table->timestamp('geactiveerd_op')->nullable()->after('is_geactiveerd');
        });
    }

    public function down(): void
    {
        Schema::table('coach_kaarten', function (Blueprint $table) {
            $table->dropColumn(['foto', 'activatie_token', 'is_geactiveerd', 'geactiveerd_op']);
        });
    }
};
