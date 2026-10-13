<?php

namespace Database\Factories;

use App\Models\ExportFile;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportFile>
 */
class ExportFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->unique()->slug(3, false).'_youtube_links.txt';

        return [
            'import_batch_id' => ImportBatch::factory(),
            'type' => 'supersimple_txt',
            'filename' => $filename,
            'path' => "exports/{$filename}",
            'version' => 1,
            'items_count' => fake()->numberBetween(1, 30),
            'options' => null,
        ];
    }
}
