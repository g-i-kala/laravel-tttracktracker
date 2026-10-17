<?php

namespace App\Jobs;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Services\AiTrackListParserService;
use App\Services\AiUsageService;
use App\Services\ImportBatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Parses a batch's raw input with AI in the background and stores the items.
 * Not retried automatically (each attempt costs money); the user can retry from Parse Review.
 * Silently dropped when the batch was deleted before the job ran.
 */
#[DeleteWhenMissingModels]
class ParseImportBatchWithAiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Must stay below the database queue's `retry_after` (DB_QUEUE_RETRY_AFTER=300).
     */
    public int $timeout = 240;

    public function __construct(public ImportBatch $batch) {}

    public function handle(AiTrackListParserService $aiParser, ImportBatchService $importBatches): void
    {
        if ($this->batch->status !== ImportBatchStatus::Parsing) {
            return;
        }

        $parsedLines = Context::scope(
            fn (): array => $aiParser->parse($this->batch->raw_input),
            [AiUsageService::BATCH_CONTEXT_KEY => $this->batch->id],
        );

        $importBatches->storeParsedLines($this->batch, $parsedLines);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('AI track list parsing failed', [
            'import_batch_id' => $this->batch->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->batch->update(['status' => ImportBatchStatus::Failed]);
    }
}
