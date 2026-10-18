<?php

namespace App\Services;

use App\Enums\YoutubeApiCallStatus;
use App\Models\YoutubeApiCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;

/**
 * Reads the videos of a public or unlisted YouTube playlist through the YouTube Data API
 * (`playlistItems.list`, 1 quota unit per page of 50 videos). Private playlists need OAuth
 * and are not supported. Every request is recorded in `youtube_api_calls`.
 */
class YoutubePlaylistService
{
    private const string API_URL = 'https://www.googleapis.com/youtube/v3/playlistItems';

    public const string ENDPOINT = 'playlistItems.list';

    /**
     * Quota cost of one `playlistItems.list` request.
     */
    public const int PAGE_QUOTA_UNITS = 1;

    /**
     * Maximum page size allowed by the API.
     */
    public const int PAGE_SIZE = 50;

    /**
     * Playlist IDs are longer than video IDs (11 characters), e.g. `PL…`, `OLAK5uy_…`, `UU…`.
     */
    private const string PLAYLIST_ID_PATTERN = '/^[A-Za-z0-9_-]{12,64}$/';

    /**
     * @var list<string>
     */
    private const array YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be'];

    /**
     * Only these videos can be played; deleted videos have `privacyStatusUnspecified`.
     *
     * @var list<string>
     */
    private const array PLAYABLE_STATUSES = ['public', 'unlisted'];

    public function __construct(private YoutubeSearchService $youtubeSearch) {}

    /**
     * The playlist ID from a playlist link (`playlist?list=`, also a `watch?v=…&list=` link)
     * or a bare playlist ID. Pure: no network access.
     */
    public function playlistId(string $url): ?string
    {
        $url = trim($url);

        if (preg_match(self::PLAYLIST_ID_PATTERN, $url)) {
            return $url;
        }

        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = "https://{$url}";
        }

        $parts = parse_url($url);

        if ($parts === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ! in_array(strtolower($parts['host'] ?? ''), self::YOUTUBE_HOSTS, true)) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $list = $query['list'] ?? null;

        return is_string($list) && preg_match(self::PLAYLIST_ID_PATTERN, $list) ? $list : null;
    }

    /**
     * Fetch playable videos in playlist order, at most `$limit`. Pages are requested only until the limit is reached.
     * `unavailable`: deleted or private videos skipped; `notImported`: videos left out because of the limit.
     *
     * @return array{videos: list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, position: int}>, unavailable: int, notImported: int}
     *
     * @throws YoutubePlaylistException
     */
    public function videos(string $url, int $limit): array
    {
        $playlistId = $this->playlistId($url);

        if ($playlistId === null) {
            throw new YoutubePlaylistException('Not a YouTube playlist link.', 'invalidPlaylistUrl');
        }

        if (! $this->youtubeSearch->isEnabled()) {
            throw new YoutubePlaylistException('YouTube Data API key is not set.', 'missingApiKey');
        }

        if ($this->youtubeSearch->isQuotaExceeded()) {
            throw new YoutubePlaylistException('YouTube Data API quota exceeded.', 'quotaExceeded');
        }

        $videos = [];
        $unavailable = 0;
        $processed = 0;
        $total = 0;
        $pageToken = null;

        do {
            $response = $this->fetchPage($playlistId, $pageToken);
            $total = (int) $response->json('pageInfo.totalResults', 0);

            foreach ($response->json('items', []) as $item) {
                if (count($videos) >= $limit) {
                    break 2;
                }

                $processed++;
                $video = $this->mapItem($item);

                if ($video === null) {
                    $unavailable++;

                    continue;
                }

                $videos[] = $video;
            }

            $pageToken = $response->json('nextPageToken');
        } while (filled($pageToken) && count($videos) < $limit);

        return [
            'videos' => $videos,
            'unavailable' => $unavailable,
            'notImported' => count($videos) >= $limit ? max(0, $total - $processed) : 0,
        ];
    }

    /**
     * @throws YoutubePlaylistException
     */
    private function fetchPage(string $playlistId, ?string $pageToken): Response
    {
        $startedAt = hrtime(true);

        try {
            $response = Http::timeout(15)->withHeaders([
                'X-Goog-Api-Key' => config('services.youtube.api_key'),
            ])->get(self::API_URL, array_filter([
                'part' => 'snippet,status',
                'playlistId' => $playlistId,
                'maxResults' => self::PAGE_SIZE,
                'pageToken' => $pageToken,
            ]));
        } catch (ConnectionException) {
            $exception = new YoutubePlaylistException('Could not connect to the YouTube Data API.', 'connectionFailed');
            $this->recordCall($playlistId, $startedAt, YoutubeApiCallStatus::Failed, 0, $exception->reason);

            throw $exception;
        }

        if ($response->failed()) {
            $exception = $this->exceptionFor($response);

            if ($exception->isQuotaExceeded()) {
                if (in_array($exception->reason, ['quotaExceeded', 'dailyLimitExceeded'], true)) {
                    $this->youtubeSearch->markQuotaExceeded();
                }

                $this->recordCall($playlistId, $startedAt, YoutubeApiCallStatus::QuotaExceeded, 0, $exception->reason);
            } else {
                $this->recordCall($playlistId, $startedAt, YoutubeApiCallStatus::Failed, self::PAGE_QUOTA_UNITS, $exception->reason);
            }

            throw $exception;
        }

        $this->recordCall($playlistId, $startedAt, YoutubeApiCallStatus::Succeeded, self::PAGE_QUOTA_UNITS, null, count($response->json('items', [])));

        return $response;
    }

    /**
     * `snippet.channelTitle` is the playlist owner; the video's own channel is `videoOwnerChannelTitle`.
     *
     * @param  array<string, mixed>  $item
     * @return array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, position: int}|null
     */
    private function mapItem(array $item): ?array
    {
        $videoId = $item['snippet']['resourceId']['videoId'] ?? null;

        if (blank($videoId) || ! in_array($item['status']['privacyStatus'] ?? null, self::PLAYABLE_STATUSES, true)) {
            return null;
        }

        return [
            'video_id' => $videoId,
            'url' => 'https://www.youtube.com/watch?v='.$videoId,
            'title' => $this->decode($item['snippet']['title'] ?? ''),
            'channel_title' => $this->decodeOrNull($item['snippet']['videoOwnerChannelTitle'] ?? null),
            'description' => $this->decodeOrNull($item['snippet']['description'] ?? null),
            'position' => (int) ($item['snippet']['position'] ?? 0),
        ];
    }

    private function recordCall(string $playlistId, int $startedAt, YoutubeApiCallStatus $status, int $quotaUnits, ?string $errorReason, ?int $resultsCount = null): void
    {
        YoutubeApiCall::create([
            'import_batch_id' => Context::get(YoutubeSearchService::BATCH_CONTEXT_KEY),
            'endpoint' => self::ENDPOINT,
            'query' => $playlistId,
            'quota_units' => $quotaUnits,
            'status' => $status,
            'error_reason' => $errorReason,
            'results_count' => $resultsCount,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
        ]);
    }

    /**
     * The error message is not used, so the API key can never end up in logs or the UI.
     */
    private function exceptionFor(Response $response): YoutubePlaylistException
    {
        $reason = $response->json('error.errors.0.reason');

        if ($reason === null && $response->status() === 404) {
            $reason = 'playlistNotFound';
        }

        return new YoutubePlaylistException("YouTube Data API error (HTTP {$response->status()}).", $reason);
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
