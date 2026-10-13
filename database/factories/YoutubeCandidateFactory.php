<?php

namespace Database\Factories;

use App\Models\ImportItem;
use App\Models\YoutubeCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoutubeCandidate>
 */
class YoutubeCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $videoId = fake()->regexify('[A-Za-z0-9_-]{11}');

        return [
            'import_item_id' => ImportItem::factory(),
            'video_id' => $videoId,
            'url' => "https://www.youtube.com/watch?v={$videoId}",
            'title' => fake()->sentence(4),
            'channel_title' => fake()->company(),
            'description' => fake()->paragraph(),
            'published_at' => fake()->dateTimeBetween('-5 years'),
            'score' => fake()->numberBetween(0, 100),
            'rank' => 1,
        ];
    }
}
