<?php

namespace Database\Factories;

use App\Models\YoutubeSearchCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoutubeSearchCache>
 */
class YoutubeSearchCacheFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $query = fake()->unique()->name().' '.fake()->words(2, true);

        return [
            'query_hash' => hash('sha256', $query),
            'query' => $query,
            'results' => [],
            'provider' => 'youtube_data_api',
            'expires_at' => now()->addDays(30),
        ];
    }

    /**
     * Indicate that the cache entry has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
