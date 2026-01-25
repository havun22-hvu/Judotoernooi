<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toernooi_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisator_id')->constrained('organisators')->cascadeOnDelete();
            $table->string('naam'); // e.g., "Intern toernooi", "Open toernooi"
            $table->text('beschrijving')->nullable();

            // All tournament settings as JSON (complete snapshot)
            $table->json('instellingen')->nullable(); // Contains all toernooi settings

            // Quick access to common settings (also in instellingen JSON)
            $table->integer('max_judokas')->nullable();
            $table->decimal('inschrijfgeld', 8, 2)->nullable();
            $table->boolean('betaling_actief')->default(false);
            $table->string('portal_modus')->default('volledig');

            $table->timestamps();

            $table->unique(['organisator_id', 'naam']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toernooi_templates');
    }
};
