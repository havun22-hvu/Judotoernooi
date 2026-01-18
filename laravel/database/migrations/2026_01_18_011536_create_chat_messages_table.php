<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained()->onDelete('cascade');

            // Afzender: hoofdjury, mat, weging, spreker, dojo
            $table->string('van_type', 20);
            $table->unsignedInteger('van_id')->nullable(); // mat nummer, null voor hoofdjury

            // Ontvanger: hoofdjury, mat, weging, spreker, dojo, alle_matten, iedereen
            $table->string('naar_type', 20);
            $table->unsignedInteger('naar_id')->nullable(); // mat nummer, null voor broadcast

            $table->text('bericht');
            $table->timestamp('gelezen_op')->nullable();
            $table->timestamps();

            // Indexes voor snelle queries
            $table->index(['toernooi_id', 'naar_type', 'naar_id']);
            $table->index(['toernooi_id', 'van_type', 'van_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
