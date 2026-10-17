<?php

namespace App\Services;

use App\Enums\YoutubeApiCallStatus;
use App\Models\YoutubeApiCall;
use App\Models\YoutubeSearchCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Searches YouTube videos through the YouTube Data API (`search.list`, 100 quota units per call).
 * Results are cached in `youtube_search_cache`; a valid cache entry means no API call.
 * Every real request is recorded in `youtube_api_calls` to estimate the daily quota use.
 */
class YoutubeSearchService
{
    private const string API_URL = 'https://www.googleapis.com/youtube/v3/search';

    public const string PROVIDER = 'youtube_data_api';

    /**
     * Results fetched per search. The quota cost does not depend on it, and more
     * results give scoring more to choose from; only the best candidates are stored.
     */
    public const int MAX_RESULTS = 10;

    public const string ENDPOINT = 'search.list';

    /**
     * Quota cost of one `search.list` request. Google charges failed requests too,
     * except when the quota is already used up.
     */
    public const int SEARCH_QUOTA_UNITS = 100;

    /**
     * Context key that links API calls to the import batch being searched.
     */
    public const string BATCH_CONTEXT_KEY = 'import_batch_id';

    public function isEnabled(): bool
    {
        return filled(config('services.youtube.api_key'));
    }

    /**
     * Search videos for a query (cache first).
     *
     * @return list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, published_at: ?string}>
     *
     * @throws YoutubeSearchException when the API fails or the quota is exceeded
     */
    public function search(string $query): array
    {
        $query = Str::squish($query);
        $queryHash = $this->queryHash($query);

        $cached = YoutubeSearchCache::query()
            ->where('query_hash', $queryHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached !== null) {
            return $cached->results;
        }

        $results = $this->fetch($query);

        YoutubeSearchCache::query()->updateOrCreate(['query_hash' => $queryHash], [
            'query' => $query,
            'results' => $results,
            'provider' => self::PROVIDER,
            'expires_at' => now()->addDays(config('services.youtube.cache_ttl_days')),
        ]);

        return $results;
    }

    /**
     * Same hash for queries that differ only in case or whitespace.
     */
    public function queryHash(string $query): string
    {
        return hash('sha256', Str::lower(Str::squish($query)));
    }

    /**
     * @return list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, published_at: ?string}>
     *
     * @throws YoutubeSearchException
     */
    private function fetch(string $query): array
    {
        if (! $this->isEnabled()) {
            throw new YoutubeSearchException('YouTube Data API key is not set.', 'missingApiKey');
        }

        $startedAt = hrtime(true);

        try {
            $response = Http::timeout(15)->withHeaders([
                'X-Goog-Api-Key' => config('services.youtube.api_key'),
            ])->get(self::API_URL, [
                'part' => 'snippet',
                'type' => 'video',
                'maxResults' => self::MAX_RESULTS,
                'q' => $query,
            ]);
        } catch (ConnectionException) {
            $exception = new YoutubeSearchException('Could not connect to the YouTube Data API.', 'connectionFailed');
            $this->recordCall($query, $startedAt, YoutubeApiCallStatus::Failed, 0, $exception->reason);

            throw $exception;
        }

        if ($response->failed()) {
            $exception = $this->exceptionFor($response);

            if ($exception->isQuotaExceeded()) {
                $this->recordCall($query, $startedAt, YoutubeApiCallStatus::QuotaExceeded, 0, $exception->reason);
            } else {
                $this->recordCall($query, $startedAt, YoutubeApiCallStatus::Failed, self::SEARCH_QUOTA_UNITS, $exception->reason);
            }

            throw $exception;
        }

        $results = $this->mapResults($response);
        $this->recordCall($query, $startedAt, YoutubeApiCallStatus::Succeeded, self::SEARCH_QUOTA_UNITS, null, count($results));

        return $results;
    }

    /**
     * @return list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, published_at: ?string}>
     */
    private function mapResults(Response $response): array
    {
        return collect($response->json('items', []))
            ->filter(fn (array $item): bool => filled($item['id']['videoId'] ?? null))
            ->map(fn (array $item): array => [
                'video_id' => $item['id']['videoId'],
                'url' => 'https://www.youtube.com/watch?v='.$item['id']['videoId'],
                'title' => $this->decode($item['snippet']['title'] ?? ''),
                'channel_title' => $this->decodeOrNull($item['snippet']['channelTitle'] ?? null),
                'description' => $this->decodeOrNull($item['snippet']['description'] ?? null),
                'published_at' => $item['snippet']['publishedAt'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function recordCall(string $query, int $startedAt, YoutubeApiCallStatus $status, int $quotaUnits, ?string $errorReason, ?int $resultsCount = null): void
    {
        YoutubeApiCall::create([
            'import_batch_id' => Context::get(self::BATCH_CONTEXT_KEY),
            'endpoint' => self::ENDPOINT,
            'query' => $query,
            'quota_units' => $quotaUnits,
            'status' => $status,
            'error_reason' => $errorReason,
            'results_count' => $resultsCount,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
        ]);
    }

    /**
     * The key is sent in a header, not in the URL, so it never shows up in exception messages.
     * Error body: `{"error": {"code": 403, "message": "…", "errors": [{"reason": "quotaExceeded"}]}}`.
     * The message is not used, so the API key can never end up in logs or the UI.
     */
    private function exceptionFor(Response $response): YoutubeSearchException
    {
        $reason = $response->json('error.errors.0.reason');
        $exception = new YoutubeSearchException("YouTube Data API error (HTTP {$response->status()}).", $reason);

        if ($exception->isQuotaExceeded()) {
            return new YoutubeSearchException('YouTube Data API quota exceeded.', $reason);
        }

        return $exception;
    }

    /**
     * The API returns HTML-encoded text (`&amp;`, `&#39;`).
     */
    private function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function decodeOrNull(?string $text): ?string
    {
        return blank($text) ? null : $this->decode($text);
    }
}
