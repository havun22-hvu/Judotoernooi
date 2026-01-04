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
        Schema::create('device_toegangen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toernooi_id')->constrained('toernooien')->onDelete('cascade');
            $table->enum('rol', ['hoofdjury', 'mat', 'weging', 'spreker', 'dojo']);
            $table->unsignedTinyInteger('mat_nummer')->nullable(); // Only for rol='mat'
            $table->string('code', 12)->unique(); // Unique URL code
            $table->string('pincode', 4); // 4-digit PIN
            $table->string('device_token', 64)->nullable(); // Bound device token
            $table->string('device_info', 255)->nullable(); // "iPhone Safari" etc.
            $table->timestamp('gebonden_op')->nullable();
            $table->timestamp('laatst_actief')->nullable();
            $table->timestamps();

            $table->index(['toernooi_id', 'rol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_toegangen');
    }
};
