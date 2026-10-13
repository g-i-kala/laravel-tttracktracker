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
        Schema::table('import_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('bpm')->nullable();
            $table->string('musical_key', 50)->nullable();
            $table->string('camelot_key', 3)->nullable();
            $table->string('label')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_items', function (Blueprint $table) {
            $table->dropColumn(['bpm', 'musical_key', 'camelot_key', 'label']);
        });
    }
};
