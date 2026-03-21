<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->string('afkorting', 10)->nullable();
            $table->string('plaats')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique('naam');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
