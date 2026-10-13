<?php

namespace Database\Factories;

use App\Ai\Agents\TrackListParser;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
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
            'invocation_id' => (string) Str::uuid7(),
            'agent' => TrackListParser::class,
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
            'input_tokens' => 1000,
            'cached_input_tokens' => 0,
            'output_tokens' => 500,
            'cost_usd' => '0.001200',
            'duration_ms' => fake()->numberBetween(1000, 10000),
            'status' => AiUsageStatus::Succeeded,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the call failed (no tokens, no cost).
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => '0.000000',
            'status' => AiUsageStatus::Failed,
            'error_message' => 'Request timed out.',
        ]);
    }
}
