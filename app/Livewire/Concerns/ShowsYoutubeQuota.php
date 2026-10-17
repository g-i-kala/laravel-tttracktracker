<?php

namespace App\Livewire\Concerns;

use App\Services\YoutubeQuotaService;
use App\Services\YoutubeSearchService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;

/**
 * Today's YouTube quota for the `<x-youtube-quota>` line; null when there is no API key
 * (no-key mode spends no quota).
 */
trait ShowsYoutubeQuota
{
    /**
     * @return array{used: int, limit: int, remaining: int, searchesLeft: int, usage: float, isUsedUp: bool, resetsAt: CarbonImmutable}|null
     */
    #[Computed]
    public function youtubeQuota(): ?array
    {
        if (! app(YoutubeSearchService::class)->isEnabled()) {
            return null;
        }

        return app(YoutubeQuotaService::class)->today();
    }
}
