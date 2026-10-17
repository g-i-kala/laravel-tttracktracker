<?php

namespace App\Services;

use App\Models\YoutubeApiCall;
use Carbon\CarbonImmutable;

/**
 * Today's estimated YouTube Data API quota use, from `youtube_api_calls`.
 * The quota day starts at midnight Pacific Time (09:00 in Warsaw most of the year).
 */
class YoutubeQuotaService
{
    public function __construct(private YoutubeSearchService $youtubeSearch) {}

    /**
     * @return array{used: int, limit: int, remaining: int, searchesLeft: int, usage: float, isUsedUp: bool, resetsAt: CarbonImmutable}
     */
    public function today(): array
    {
        $dayStart = CarbonImmutable::now(YoutubeSearchService::QUOTA_TIMEZONE)->startOfDay();
        $appTimezone = config('app.timezone');

        $used = (int) YoutubeApiCall::query()
            ->where('created_at', '>=', $dayStart->setTimezone($appTimezone))
            ->sum('quota_units');

        $limit = max(1, (int) config('services.youtube.daily_quota'));
        $remaining = max(0, $limit - $used);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'searchesLeft' => intdiv($remaining, YoutubeSearchService::SEARCH_QUOTA_UNITS),
            'usage' => $used / $limit,
            'isUsedUp' => $remaining < YoutubeSearchService::SEARCH_QUOTA_UNITS || $this->youtubeSearch->isQuotaExceeded(),
            'resetsAt' => $dayStart->addDay()->setTimezone($appTimezone),
        ];
    }
}
