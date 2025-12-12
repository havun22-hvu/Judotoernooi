<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('toernooi_id')->constrained('toernooien')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('naam');
            $table->string('email')->nullable();
            $table->string('telefoon', 20)->nullable();
            $table->string('pincode', 6); // 4-6 digit pincode
            $table->timestamp('laatst_ingelogd_op')->nullable();
            $table->timestamps();

            // Max 3 coaches per club per toernooi
            $table->unique(['club_id', 'toernooi_id', 'naam']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};
