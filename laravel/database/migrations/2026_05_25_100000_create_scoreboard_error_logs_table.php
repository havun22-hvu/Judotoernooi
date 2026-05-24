<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoreboard_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->text('stack')->nullable();
            $table->string('screen')->nullable();
            $table->timestamp('app_timestamp')->nullable();
            $table->string('app_version', 20)->nullable();
            $table->boolean('fatal')->default(false);
            $table->string('device', 20)->nullable();
            $table->string('platform_version', 20)->nullable();
            $table->foreignId('device_toegang_id')->nullable()->constrained('device_toegangen')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['fatal', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoreboard_error_logs');
    }
};
