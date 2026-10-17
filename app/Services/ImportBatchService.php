<?php

namespace App\Services;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Jobs\ParseImportBatchWithAiJob;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\YoutubeCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creates import batches and their items from pasted track lists, with the
 * line parser (synchronous) or with AI (queued), and moves batches through
 * review, duplicate removal and the no-key YouTube search.
 */
class ImportBatchService
{
    public function __construct(
        private ImportParserService $parser,
        private DuplicateDetectionService $duplicates,
        private YoutubeSearchUrlService $youtubeSearchUrls,
    ) {}

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
     * Delete exact duplicates, keeping the first item (lowest line number) of each group.
     *
     * @return int number of deleted items
     */
    public function removeExactDuplicates(ImportBatch $batch): int
    {
        $duplicateIds = array_keys($this->duplicates->exactDuplicates($batch->items()->get()));

        if ($duplicateIds === []) {
            return 0;
        }

        return $batch->items()->whereKey($duplicateIds)->delete();
    }

    /**
     * No-key YouTube search: store a search URL on every item and mark items and
     * the batch as searched. Running it again (after edits in Parse Review)
     * regenerates the URLs; selected links and exported statuses are kept.
     */
    public function generateYoutubeSearchUrls(ImportBatch $batch): void
    {
        DB::transaction(function () use ($batch): void {
            foreach ($batch->items()->get() as $item) {
                $item->youtube_search_url = $this->youtubeSearchUrls->url($item->artist, $item->title);

                if ($item->status !== ImportItemStatus::Exported) {
                    $item->status = ImportItemStatus::Searched;
                }

                $item->save();
            }

            if ($batch->status !== ImportBatchStatus::Exported) {
                $batch->update(['status' => ImportBatchStatus::Searched]);
            }
        });
    }

    /**
     * What deleting these batches removes, for the confirmation modal.
     *
     * @param  list<int>  $batchIds
     * @return array{imports: int, tracks: int, exports: int}
     */
    public function deletionSummary(array $batchIds): array
    {
        return [
            'imports' => ImportBatch::query()->whereKey($batchIds)->count(),
            'tracks' => ImportItem::query()->whereIn('import_batch_id', $batchIds)->count(),
            'exports' => ExportFile::query()->whereIn('import_batch_id', $batchIds)->count(),
        ];
    }

    /**
     * Delete batches with their YouTube candidates, items, export rows and TXT
     * files (foreign keys restrict, so the order matters). AI usage logs stay,
     * their batch reference becomes null. Files are removed after the transaction.
     *
     * @param  list<int>  $batchIds
     * @return int number of deleted batches
     */
    public function deleteBatches(array $batchIds): int
    {
        $batchIds = ImportBatch::query()->whereKey($batchIds)->pluck('id')->all();

        if ($batchIds === []) {
            return 0;
        }

        $paths = ExportFile::query()->whereIn('import_batch_id', $batchIds)->pluck('path')->all();

        $deleted = DB::transaction(function () use ($batchIds): int {
            $itemIds = ImportItem::query()->whereIn('import_batch_id', $batchIds)->select('id');

            YoutubeCandidate::query()->whereIn('import_item_id', $itemIds)->delete();
            ImportItem::query()->whereIn('import_batch_id', $batchIds)->delete();
            ExportFile::query()->whereIn('import_batch_id', $batchIds)->delete();

            return ImportBatch::query()->whereKey($batchIds)->delete();
        });

        $disk = Storage::disk(SupersimpleTxtExportService::DISK);
        $disk->delete($paths);

        foreach ($batchIds as $batchId) {
            $disk->deleteDirectory("exports/{$batchId}");
        }

        return $deleted;
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
