<?php

namespace Database\Factories;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'YouTube Export '.fake()->dateTimeThisYear()->format('Y-m-d H:i'),
            'source_type' => 'manual',
            'default_genre' => null,
            'year' => null,
            'month' => null,
            'status' => ImportBatchStatus::Draft,
            'raw_input' => collect(range(1, 3))
                ->map(fn (): string => fake()->name().' - '.fake()->words(3, true))
                ->implode("\n"),
        ];
    }

    /**
     * Indicate that the batch has been parsed into items.
     */
    public function parsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportBatchStatus::Parsed,
        ]);
    }

    /**
     * Indicate that the parsed items have been reviewed (Save & close).
     */
    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportBatchStatus::Reviewed,
        ]);
    }
}
