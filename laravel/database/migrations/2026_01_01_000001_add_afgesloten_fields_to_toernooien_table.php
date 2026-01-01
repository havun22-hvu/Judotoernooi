<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->timestamp('afgesloten_at')->nullable()->after('datum');
            $table->date('herinnering_datum')->nullable()->after('afgesloten_at');
            $table->boolean('herinnering_verstuurd')->default(false)->after('herinnering_datum');
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn(['afgesloten_at', 'herinnering_datum', 'herinnering_verstuurd']);
        });
    }
};
