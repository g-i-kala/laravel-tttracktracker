<?php

namespace App\Services;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Creates import batches and their items from pasted track lists.
 */
class ImportBatchService
{
    public function __construct(private ImportParserService $parser) {}

    /**
     * Create a batch from raw input, parse it and store one item per detected line.
     * Items get the batch's default genre.
     *
     * @param  array{name: string, year: ?int, month: ?int, default_genre: ?string, raw_input: string}  $attributes
     */
    public function createFromRawInput(array $attributes): ImportBatch
    {
        $parsedLines = $this->parser->parse($attributes['raw_input']);

        return DB::transaction(function () use ($attributes, $parsedLines): ImportBatch {
            $batch = ImportBatch::create([
                ...$attributes,
                'source_type' => 'manual',
                'status' => ImportBatchStatus::Parsed,
            ]);

            $batch->items()->createMany(array_map(fn (array $line): array => [
                ...$line,
                'genre' => $attributes['default_genre'],
                'status' => ImportItemStatus::Parsed,
            ], $parsedLines));

            return $batch;
        });
    }
}
