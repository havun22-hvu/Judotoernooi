<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // security, queue_failure, slow_query, health_degraded, autofix
            $table->string('severity'); // critical, high, medium, low
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['is_read', 'created_at']);
            $table->index('type');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
