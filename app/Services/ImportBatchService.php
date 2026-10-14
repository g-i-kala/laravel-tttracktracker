<?php

namespace App\Services;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Jobs\ParseImportBatchWithAiJob;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Creates import batches and their items from pasted track lists, with the
 * line parser (synchronous) or with AI (queued).
 */
class ImportBatchService
{
    public function __construct(private ImportParserService $parser) {}

    /**
     * Create a batch from raw input, parse it with the line parser and store one item per detected line.
     *
     * @param  array{name: string, year: ?int, month: ?int, default_genre: ?string, raw_input: string}  $attributes
     */
    public function createFromRawInput(array $attributes): ImportBatch
    {
        return DB::transaction(function () use ($attributes): ImportBatch {
            $batch = $this->createBatch($attributes, ImportBatchStatus::Parsing);

            $this->storeParsedLines($batch, $this->parser->parse($attributes['raw_input']));

            return $batch;
        });
    }

    /**
     * Create a batch in `parsing` status and queue AI parsing of its raw input.
     *
     * @param  array{name: string, year: ?int, month: ?int, default_genre: ?string, raw_input: string}  $attributes
     */
    public function createForAiParsing(array $attributes): ImportBatch
    {
        $batch = $this->createBatch($attributes, ImportBatchStatus::Parsing);

        ParseImportBatchWithAiJob::dispatch($batch);

        return $batch;
    }

    /**
     * Queue AI parsing again, e.g. after a failure.
     */
    public function retryAiParsing(ImportBatch $batch): void
    {
        $batch->update(['status' => ImportBatchStatus::Parsing]);

        ParseImportBatchWithAiJob::dispatch($batch);
    }

    /**
     * Parse an existing batch with the line parser (fallback when AI parsing failed).
     */
    public function parseWithLineParser(ImportBatch $batch): void
    {
        $this->storeParsedLines($batch, $this->parser->parse($batch->raw_input));
    }

    /**
     * Replace the batch's items with the parsed lines and mark it as parsed.
     * Items without their own genre get the batch's default genre.
     *
     * @param  list<array{line_number: int, raw_line: string, artist: ?string, title: string, normalized_key: string, error_message: ?string, genre?: ?string, bpm?: ?int, musical_key?: ?string, camelot_key?: ?string, label?: ?string}>  $parsedLines
     */
    public function storeParsedLines(ImportBatch $batch, array $parsedLines): void
    {
        DB::transaction(function () use ($batch, $parsedLines): void {
            $batch->items()->delete();

            $batch->items()->createMany(array_map(fn (array $line): array => [
                ...$line,
                'genre' => ($line['genre'] ?? null) ?? $batch->default_genre,
                'status' => ImportItemStatus::Parsed,
            ], $parsedLines));

            $batch->update(['status' => ImportBatchStatus::Parsed]);
        });
    }

    /**
     * Mark a parsed batch as reviewed. Batches further in the pipeline keep their status.
     */
    public function markReviewed(ImportBatch $batch): void
    {
        if ($batch->status === ImportBatchStatus::Parsed) {
            $batch->update(['status' => ImportBatchStatus::Reviewed]);
        }
    }

    /**
     * @param  array{name: string, year: ?int, month: ?int, default_genre: ?string, raw_input: string}  $attributes
     */
    private function createBatch(array $attributes, ImportBatchStatus $status): ImportBatch
    {
        return ImportBatch::create([
            ...$attributes,
            'source_type' => 'manual',
            'status' => $status,
        ]);
    }
}
