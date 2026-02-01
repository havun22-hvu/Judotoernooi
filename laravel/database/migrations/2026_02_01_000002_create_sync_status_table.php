<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained()->onDelete('cascade');
            $table->enum('direction', ['cloud_to_local', 'local_to_cloud']);
            $table->timestamp('last_sync_at')->nullable();
            $table->integer('records_synced')->default(0);
            $table->enum('status', ['idle', 'syncing', 'success', 'failed'])->default('idle');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['toernooi_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_status');
    }
};
