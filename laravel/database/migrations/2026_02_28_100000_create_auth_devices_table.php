<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisator_id')->constrained('organisators')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('pin_hash')->nullable();
            $table->boolean('has_biometric')->default(false);
            $table->string('device_fingerprint', 64)->nullable();
            $table->string('device_name')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['token', 'is_active', 'expires_at']);
            $table->index('device_fingerprint');
            $table->index('organisator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_devices');
    }
};
