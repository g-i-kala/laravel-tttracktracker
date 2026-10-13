<?php

namespace Database\Factories;

use App\Enums\ImportItemStatus;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImportItem>
 */
class ImportItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $artist = fake()->name();
        $title = Str::title(fake()->words(2, true)).' (Original Mix)';

        return [
            'import_batch_id' => ImportBatch::factory(),
            'line_number' => fake()->numberBetween(1, 30),
            'raw_line' => "{$artist} - {$title}",
            'artist' => $artist,
            'title' => $title,
            'genre' => null,
            'normalized_key' => Str::of("{$artist} {$title}")->lower()->squish()->toString(),
            'status' => ImportItemStatus::Parsed,
            'include_in_export' => false,
        ];
    }

    /**
     * Indicate that the item has store metadata (as parsed by AI from a Beatport paste).
     */
    public function withMetadata(): static
    {
        return $this->state(fn (array $attributes) => [
            'genre' => 'Deep House',
            'bpm' => 128,
            'musical_key' => 'E Minor',
            'camelot_key' => '9A',
            'label' => 'tszr',
        ]);
    }

    /**
     * Indicate that the item has a selected YouTube link and is included in the export.
     */
    public function withSelectedYoutubeLink(): static
    {
        return $this->state(function (array $attributes) {
            $videoId = fake()->regexify('[A-Za-z0-9_-]{11}');

            return [
                'status' => ImportItemStatus::Searched,
                'selected_youtube_url' => "https://www.youtube.com/watch?v={$videoId}",
                'selected_youtube_video_id' => $videoId,
                'selected_youtube_title' => "{$attributes['artist']} - {$attributes['title']}",
                'selected_youtube_channel' => "{$attributes['artist']} - Topic",
                'youtube_score' => 90,
                'include_in_export' => true,
            ];
        });
    }

    /**
     * Indicate that the item was searched in no-key mode and has only a search URL.
     */
    public function withSearchUrlOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportItemStatus::Searched,
            'youtube_search_url' => 'https://www.youtube.com/results?search_query='
                .urlencode("{$attributes['artist']} {$attributes['title']}"),
        ]);
    }
}
