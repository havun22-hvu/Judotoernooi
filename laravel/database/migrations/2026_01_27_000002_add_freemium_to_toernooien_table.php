<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->string('plan_type')->default('free')->after('organisator_id'); // free, paid
            $table->string('paid_tier')->nullable()->after('plan_type'); // e.g. '51-100'
            $table->integer('paid_max_judokas')->nullable()->after('paid_tier');
            $table->timestamp('paid_at')->nullable()->after('paid_max_judokas');
            $table->foreignId('toernooi_betaling_id')->nullable()->after('paid_at')
                ->constrained('toernooi_betalingen')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropForeign(['toernooi_betaling_id']);
            $table->dropColumn(['plan_type', 'paid_tier', 'paid_max_judokas', 'paid_at', 'toernooi_betaling_id']);
        });
    }
};
