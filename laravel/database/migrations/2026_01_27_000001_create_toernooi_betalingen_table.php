<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toernooi_betalingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->onDelete('cascade');
            $table->foreignId('organisator_id')->constrained('organisators')->onDelete('cascade');
            $table->string('mollie_payment_id')->unique();
            $table->decimal('bedrag', 8, 2);
            $table->string('tier'); // e.g. '51-100', '101-150', etc.
            $table->integer('max_judokas');
            $table->string('status')->default('open'); // open, paid, failed, expired, canceled
            $table->timestamp('betaald_op')->nullable();
            $table->timestamps();

            $table->index(['toernooi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toernooi_betalingen');
    }
};
