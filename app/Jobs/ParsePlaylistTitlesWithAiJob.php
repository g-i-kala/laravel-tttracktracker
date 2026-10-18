<?php

namespace App\Jobs;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Services\AiUsageService;
use App\Services\ImportBatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Improves artist/title of a playlist import's items with AI, in place (links stay).
 * Not retried (each attempt costs money). On failure the simple-parser result stays
 * and the batch becomes `parsed`. Silently dropped when the batch was deleted.
 */
#[DeleteWhenMissingModels]
class ParsePlaylistTitlesWithAiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Must stay below the database queue's `retry_after` (DB_QUEUE_RETRY_AFTER=300).
     */
    public int $timeout = 240;

    public function __construct(public ImportBatch $batch) {}

    public function handle(ImportBatchService $importBatches): void
    {
        if ($this->batch->status !== ImportBatchStatus::Parsing) {
            return;
        }

        Context::scope(
            fn () => $importBatches->applyAiPlaylistTitles($this->batch),
            [AiUsageService::BATCH_CONTEXT_KEY => $this->batch->id],
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('AI playlist title parsing failed', [
            'import_batch_id' => $this->batch->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->batch->update(['status' => ImportBatchStatus::Parsed]);
    }
}
