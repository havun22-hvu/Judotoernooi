<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->string('telefoon', 20)->nullable()->after('opmerking');
        });
    }

    public function down(): void
    {
        Schema::table('judokas', function (Blueprint $table) {
            $table->dropColumn('telefoon');
        });
    }
};
