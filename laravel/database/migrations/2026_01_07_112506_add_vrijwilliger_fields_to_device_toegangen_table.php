<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_toegangen', function (Blueprint $table) {
            $table->string('naam')->after('toernooi_id');
            $table->string('telefoon', 20)->nullable()->after('naam');
            $table->string('email')->nullable()->after('telefoon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_toegangen', function (Blueprint $table) {
            $table->dropColumn(['naam', 'telefoon', 'email']);
        });
    }
};
