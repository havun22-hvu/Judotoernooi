<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autofix_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('exception_class');
            $table->text('exception_message');
            $table->string('file');
            $table->unsignedInteger('line');
            $table->text('stack_trace');
            $table->longText('code_context');
            $table->longText('claude_analysis')->nullable();
            $table->longText('proposed_diff')->nullable();
            $table->string('approval_token', 64)->unique();
            $table->enum('status', ['pending', 'approved', 'rejected', 'applied', 'failed'])->default('pending');
            $table->string('url')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('apply_error')->nullable();
            $table->timestamps();

            $table->index(['exception_class', 'file', 'line']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autofix_proposals');
    }
};
