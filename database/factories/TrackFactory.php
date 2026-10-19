<?php

namespace Database\Factories;

use App\Models\Track;
use App\Services\ImportParserService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artist' => fake()->name(),
            'title' => Str::title(fake()->words(2, true)),
            'normalized_key' => fn (array $attributes): string => (new ImportParserService)->normalizedKey($attributes['artist'], $attributes['title']),
            'similar_key' => fn (array $attributes): string => (new ImportParserService)->similarKey($attributes['artist'], $attributes['title']),
        ];
    }

    /**
     * Indicate that the track has store metadata (as parsed by AI from a Beatport paste).
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
     * Indicate that the track has a main YouTube link (playable online).
     */
    public function withYoutubeLink(): static
    {
        return $this->state(fn (array $attributes) => [
            'youtube_url' => 'https://www.youtube.com/watch?v='.fake()->regexify('[A-Za-z0-9_-]{11}'),
        ]);
    }

    /**
     * Indicate that the user has a file of the track (playable offline).
     */
    public function playableOffline(): static
    {
        return $this->state(fn (array $attributes) => [
            'playable_offline' => true,
        ]);
    }

    /**
     * Indicate that the user wants a file of the track.
     */
    public function wanted(): static
    {
        return $this->state(fn (array $attributes) => [
            'wanted' => true,
        ]);
    }
}
