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
        Schema::create('youtube_api_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('endpoint');
            $table->text('query');
            $table->unsignedSmallInteger('quota_units');
            $table->string('status');
            $table->string('error_reason')->nullable();
            $table->unsignedSmallInteger('results_count')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_api_calls');
    }
};
