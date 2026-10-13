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
        Schema::create('export_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('filename');
            $table->string('path');
            $table->unsignedInteger('version');
            $table->unsignedInteger('items_count');
            $table->jsonb('options')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'type', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_files');
    }
};
