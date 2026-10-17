<?php

namespace App\Jobs;

use App\Enums\ImportItemStatus;
use App\Models\ImportItem;
use App\Services\ImportItemService;
use App\Services\YoutubeScoringService;
use App\Services\YoutubeSearchException;
use App\Services\YoutubeSearchService;
use App\Services\YoutubeSearchUrlService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Searches YouTube (API mode) for one import item, part of a `Bus::batch()` started by
 * `ImportBatchService::startYoutubeSearch()`. Stores the best candidates and preselects
 * the top one by score. API errors fall back to the item's search URL with a notice;
 * the batch never fails as a whole. Silently dropped when the item was deleted.
 */
#[DeleteWhenMissingModels]
class SearchYoutubeForImportItemJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    /**
     * Must stay below the database queue's `retry_after` (DB_QUEUE_RETRY_AFTER=300).
     */
    public int $timeout = 60;

    public function __construct(public ImportItem $item) {}

    public function handle(
        YoutubeSearchService $youtubeSearch,
        YoutubeSearchUrlService $searchUrls,
        YoutubeScoringService $scoring,
        ImportItemService $items,
    ): void {
        if ($this->batch()?->cancelled() || $this->item->status !== ImportItemStatus::SearchPending) {
            return;
        }

        try {
            $results = Context::scope(
                fn (): array => $youtubeSearch->search($searchUrls->query($this->item->artist, $this->item->title)),
                [YoutubeSearchService::BATCH_CONTEXT_KEY => $this->item->import_batch_id],
            );
        } catch (YoutubeSearchException $exception) {
            $items->markYoutubeSearchFailed($this->item, $exception->isQuotaExceeded()
                ? ImportItemService::QUOTA_EXCEEDED_NOTICE
                : ImportItemService::SEARCH_FAILED_NOTICE);

            return;
        }

        $candidates = $scoring->rank(
            $this->item->artist,
            $this->item->title,
            $this->item->label,
            $results,
            config('services.youtube.candidates_limit'),
        );

        $items->storeYoutubeCandidates($this->item, $candidates);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('YouTube search for an import item failed', [
            'import_item_id' => $this->item->id,
            'error' => $exception?->getMessage(),
        ]);

        app(ImportItemService::class)->markYoutubeSearchFailed($this->item, ImportItemService::SEARCH_FAILED_NOTICE);
    }
}
