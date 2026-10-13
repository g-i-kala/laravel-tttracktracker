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
        Schema::create('youtube_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_item_id')->constrained()->restrictOnDelete();
            $table->string('video_id', 20);
            $table->string('url');
            $table->string('title');
            $table->string('channel_title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('rank');
            $table->timestamps();

            $table->index(['import_item_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_candidates');
    }
};
