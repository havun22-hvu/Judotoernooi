<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Club API tokens for the HavunClub integration.
 *
 * JudoToernooi has no separate "club"/tenant model: the Organisator IS the tenant.
 * A token maps an external caller (HavunClub) to exactly one Organisator, so no
 * tenant parameter is needed in requests — the token scopes everything.
 *
 * Additive only: solo JudoToernooi never creates a token and is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisator_id')->constrained('organisators')->cascadeOnDelete();
            $table->string('token', 80)->unique();
            $table->string('label')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['token', 'actief']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_api_tokens');
    }
};
