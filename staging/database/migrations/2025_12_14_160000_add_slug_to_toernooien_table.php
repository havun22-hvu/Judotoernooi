<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('naam');
        });

        // Generate slugs for existing tournaments
        $toernooien = \App\Models\Toernooi::all();
        foreach ($toernooien as $toernooi) {
            $baseSlug = Str::slug($toernooi->naam);
            $slug = $baseSlug;
            $counter = 1;

            while (\App\Models\Toernooi::where('slug', $slug)->where('id', '!=', $toernooi->id)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $toernooi->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('toernooien', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
