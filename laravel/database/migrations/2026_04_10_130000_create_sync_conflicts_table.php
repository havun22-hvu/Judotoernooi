<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sync conflict audit trail.
 *
 * When a sync item arrives where BOTH local and cloud have changed since
 * the last sync, we used to silently apply last-write-wins. That cost us
 * tournament data (live mat scores being overwritten by stale config syncs
 * and vice versa). This table records every detected conflict together
 * with both versions of the row, so an admin can review and (if needed)
 * manually correct mistakes after the fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->json('local_data');
            $table->json('cloud_data');
            $table->string('applied_winner'); // 'local' or 'cloud'
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->index(['table_name', 'record_id']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
