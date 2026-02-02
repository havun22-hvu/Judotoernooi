<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // 'uitnodiging', 'correctie', 'herinnering', etc.
            $table->string('recipients'); // comma-separated email addresses
            $table->string('subject');
            $table->text('summary')->nullable(); // short description of content
            $table->string('status')->default('sent'); // 'sent', 'failed'
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['toernooi_id', 'created_at']);
            $table->index(['toernooi_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
