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
        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->text('raw_line');
            $table->string('artist')->nullable();
            $table->string('title');
            $table->string('genre')->nullable();
            $table->string('normalized_key');
            $table->string('status')->default('parsed');
            $table->string('selected_youtube_url')->nullable();
            $table->string('selected_youtube_video_id', 20)->nullable();
            $table->string('selected_youtube_title')->nullable();
            $table->string('selected_youtube_channel')->nullable();
            $table->unsignedTinyInteger('youtube_score')->nullable();
            $table->text('youtube_search_url')->nullable();
            $table->boolean('include_in_export')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'line_number']);
            $table->index('normalized_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_items');
    }
};
