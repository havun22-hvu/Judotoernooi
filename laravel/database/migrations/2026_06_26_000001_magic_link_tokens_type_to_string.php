<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen magic_link_tokens.type from a fixed enum (register, password_reset)
     * to a free string so new types (login, ...) are allowed. Additive, no data
     * loss — existing rows keep their value.
     */
    public function up(): void
    {
        Schema::table('magic_link_tokens', function (Blueprint $table) {
            $table->string('type', 32)->default('register')->change();
        });
    }

    public function down(): void
    {
        Schema::table('magic_link_tokens', function (Blueprint $table) {
            $table->enum('type', ['register', 'password_reset'])->default('register')->change();
        });
    }
};
