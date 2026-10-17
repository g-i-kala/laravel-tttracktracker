<?php

namespace Database\Factories;

use App\Enums\YoutubeApiCallStatus;
use App\Models\YoutubeApiCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoutubeApiCall>
 */
class YoutubeApiCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_batch_id' => null,
            'endpoint' => 'search.list',
            'query' => fake()->name().' '.fake()->words(2, true),
            'quota_units' => 100,
            'status' => YoutubeApiCallStatus::Succeeded,
            'error_reason' => null,
            'results_count' => 10,
            'duration_ms' => fake()->numberBetween(200, 1500),
        ];
    }

    /**
     * Indicate that the daily quota was already used up (the request costs nothing).
     */
    public function quotaExceeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'quota_units' => 0,
            'status' => YoutubeApiCallStatus::QuotaExceeded,
            'error_reason' => 'quotaExceeded',
            'results_count' => null,
        ]);
    }
}
