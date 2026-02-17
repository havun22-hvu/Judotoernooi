<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stam_judokas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisator_id')->constrained('organisators')->cascadeOnDelete();
            $table->string('naam');
            $table->unsignedSmallInteger('geboortejaar');
            $table->char('geslacht', 1); // M / V
            $table->string('band', 20);
            $table->decimal('gewicht', 4, 1)->nullable();
            $table->text('notities')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();

            $table->unique(['organisator_id', 'naam', 'geboortejaar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stam_judokas');
    }
};
