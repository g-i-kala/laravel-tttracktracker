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
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('artist')->nullable();
            $table->string('title');
            $table->string('normalized_key')->unique();
            $table->string('similar_key')->index();
            $table->string('genre')->nullable();
            $table->unsignedSmallInteger('bpm')->nullable();
            $table->string('musical_key', 50)->nullable();
            $table->string('camelot_key', 3)->nullable();
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('playable_offline')->default(false);
            $table->boolean('wanted')->default(false);
            $table->string('youtube_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
