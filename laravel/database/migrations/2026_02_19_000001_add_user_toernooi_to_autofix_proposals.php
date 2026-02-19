<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autofix_proposals', function (Blueprint $table) {
            $table->unsignedBigInteger('organisator_id')->nullable()->after('url');
            $table->string('organisator_naam')->nullable()->after('organisator_id');
            $table->unsignedBigInteger('toernooi_id')->nullable()->after('organisator_naam');
            $table->string('toernooi_naam')->nullable()->after('toernooi_id');
            $table->string('http_method')->nullable()->after('toernooi_naam');
            $table->string('route_name')->nullable()->after('http_method');
        });
    }

    public function down(): void
    {
        Schema::table('autofix_proposals', function (Blueprint $table) {
            $table->dropColumn([
                'organisator_id',
                'organisator_naam',
                'toernooi_id',
                'toernooi_naam',
                'http_method',
                'route_name',
            ]);
        });
    }
};
