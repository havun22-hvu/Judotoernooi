<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_uitnodigingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->onDelete('cascade');
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->string('token', 64)->unique();
            $table->string('wachtwoord_hash')->nullable();
            $table->timestamp('uitgenodigd_op')->nullable();
            $table->timestamp('geregistreerd_op')->nullable();
            $table->timestamp('laatst_ingelogd_op')->nullable();
            $table->timestamps();

            $table->unique(['toernooi_id', 'club_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_uitnodigingen');
    }
};
