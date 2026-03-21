<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->boolean('heeft_eigen_router')->default(false)->after('local_server_standby_ip');
            $table->string('eigen_router_ssid', 100)->nullable()->after('heeft_eigen_router');
            $table->string('eigen_router_wachtwoord', 100)->nullable()->after('eigen_router_ssid');
            $table->string('hotspot_ssid', 100)->nullable()->after('eigen_router_wachtwoord');
            $table->string('hotspot_wachtwoord', 100)->nullable()->after('hotspot_ssid');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn([
                'heeft_eigen_router',
                'eigen_router_ssid',
                'eigen_router_wachtwoord',
                'hotspot_ssid',
                'hotspot_wachtwoord',
            ]);
        });
    }
};
