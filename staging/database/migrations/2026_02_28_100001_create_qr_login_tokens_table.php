<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('organisator_id')->nullable()->constrained('organisators')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending, approved, expired, used (string for SQLite compat)
            $table->json('device_info')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_organisator_id')->nullable()->constrained('organisators')->nullOnDelete();
            $table->timestamps();

            $table->index(['token', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_login_tokens');
    }
};
