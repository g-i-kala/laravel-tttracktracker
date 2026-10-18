<?php

namespace Tests\Feature\Youtube;

use App\Enums\YoutubeApiCallStatus;
use App\Models\YoutubeApiCall;
use App\Services\YoutubePlaylistException;
use App\Services\YoutubePlaylistService;
use App\Services\YoutubeSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YoutubePlaylistServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string API_KEY = 'test-youtube-key';

    private const string PLAYLIST_ID = 'PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG';

    private const string PLAYLIST_URL = 'https://www.youtube.com/playlist?list='.self::PLAYLIST_ID;

    private YoutubePlaylistService $youtubePlaylist;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.youtube.api_key' => self::API_KEY]);

        $this->youtubePlaylist = app(YoutubePlaylistService::class);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function playlistUrls(): array
    {
        return [
            'playlist link' => [self::PLAYLIST_URL, self::PLAYLIST_ID],
            'without scheme' => ['youtube.com/playlist?list='.self::PLAYLIST_ID, self::PLAYLIST_ID],
            'watch link with list' => ['https://www.youtube.com/watch?v=K0HSD_i2DvA&list='.self::PLAYLIST_ID.'&index=2', self::PLAYLIST_ID],
            'short link with list' => ['https://youtu.be/K0HSD_i2DvA?list='.self::PLAYLIST_ID, self::PLAYLIST_ID],
            'mobile' => ['https://m.youtube.com/playlist?list='.self::PLAYLIST_ID, self::PLAYLIST_ID],
            'YouTube Music album' => ['https://music.youtube.com/playlist?list=OLAK5uy_kXyZ12345abcdefGHIJ', 'OLAK5uy_kXyZ12345abcdefGHIJ'],
            'bare ID with spaces' => ['  '.self::PLAYLIST_ID.' ', self::PLAYLIST_ID],
            'video link without list' => ['https://www.youtube.com/watch?v=K0HSD_i2DvA', null],
            'bare video ID' => ['K0HSD_i2DvA', null],
            'other host' => ['https://example.com/playlist?list='.self::PLAYLIST_ID, null],
            'invalid list' => ['https://www.youtube.com/playlist?list=abc', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('playlistUrls')]
    public function test_playlist_id_is_read_from_links(string $url, ?string $expected): void
    {
        $this->assertSame($expected, $this->youtubePlaylist->playlistId($url));
    }

    public function test_videos_are_fetched_and_mapped(): void
    {
        Http::fake(['www.googleapis.com/youtube/v3/playlistItems*' => Http::response($this->page([
            $this->item('K0HSD_i2DvA', 'Daft Punk - Around The World (Official Music Video) &amp; &#39;Remastered&#39;', 'Daft Punk', 0),
            $this->item('xxxxxxxxxx1', 'Deleted video', null, 1, 'privacyStatusUnspecified'),
            $this->item('xxxxxxxxxx2', 'Private video', null, 2, 'private'),
            $this->item('dwDns8x3Jb4', 'Glue', 'Bicep - Topic', 3, 'unlisted', 'Provided to YouTube by Ninja Tune'),
        ], total: 4))]);

        $result = $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200);

        $this->assertSame([
            [
                'video_id' => 'K0HSD_i2DvA',
                'url' => 'https://www.youtube.com/watch?v=K0HSD_i2DvA',
                'title' => "Daft Punk - Around The World (Official Music Video) & 'Remastered'",
                'channel_title' => 'Daft Punk',
                'description' => null,
                'position' => 0,
            ],
            [
                'video_id' => 'dwDns8x3Jb4',
                'url' => 'https://www.youtube.com/watch?v=dwDns8x3Jb4',
                'title' => 'Glue',
                'channel_title' => 'Bicep - Topic',
                'description' => 'Provided to YouTube by Ninja Tune',
                'position' => 3,
            ],
        ], $result['videos']);
        $this->assertSame(2, $result['unavailable']);
        $this->assertSame(0, $result['notImported']);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Goog-Api-Key', self::API_KEY)
            && ! str_contains($request->url(), self::API_KEY)
            && $request['playlistId'] === self::PLAYLIST_ID
            && $request['part'] === 'snippet,status'
            && (int) $request['maxResults'] === 50
            && ! isset($request['pageToken']));
    }

    public function test_all_pages_are_fetched(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::sequence()
            ->push($this->page([$this->item('aaaaaaaaaa1', 'A - One', 'A', 0)], total: 2, nextPageToken: 'PAGE2'))
            ->push($this->page([$this->item('aaaaaaaaaa2', 'B - Two', 'B', 1)], total: 2))]);

        $result = $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200);

        $this->assertSame(['aaaaaaaaaa1', 'aaaaaaaaaa2'], array_column($result['videos'], 'video_id'));
        $this->assertSame(0, $result['notImported']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => ($request['pageToken'] ?? null) === 'PAGE2');
        $this->assertSame(2, (int) YoutubeApiCall::query()->sum('quota_units'));
    }

    public function test_limit_stops_fetching_and_counts_videos_left_out(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::sequence()
            ->push($this->page([
                $this->item('aaaaaaaaaa1', 'A - One', 'A', 0),
                $this->item('aaaaaaaaaa2', 'Deleted video', null, 1, 'privacyStatusUnspecified'),
                $this->item('aaaaaaaaaa3', 'C - Three', 'C', 2),
                $this->item('aaaaaaaaaa4', 'D - Four', 'D', 3),
            ], total: 120, nextPageToken: 'PAGE2'))]);

        $result = $this->youtubePlaylist->videos(self::PLAYLIST_URL, 2);

        $this->assertSame(['aaaaaaaaaa1', 'aaaaaaaaaa3'], array_column($result['videos'], 'video_id'));
        $this->assertSame(1, $result['unavailable']);
        $this->assertSame(117, $result['notImported']);
        Http::assertSentCount(1);
    }

    public function test_successful_call_is_recorded(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->page([$this->item('aaaaaaaaaa1', 'A - One', 'A', 0)], total: 1))]);

        $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200);

        $call = YoutubeApiCall::query()->sole();
        $this->assertSame('playlistItems.list', $call->endpoint);
        $this->assertSame(self::PLAYLIST_ID, $call->query);
        $this->assertSame(1, $call->quota_units);
        $this->assertSame(YoutubeApiCallStatus::Succeeded, $call->status);
        $this->assertSame(1, $call->results_count);
    }

    public function test_invalid_link_throws_without_calling_the_api(): void
    {
        Http::fake();

        try {
            $this->youtubePlaylist->videos('https://www.youtube.com/watch?v=K0HSD_i2DvA', 200);
            $this->fail('Expected YoutubePlaylistException.');
        } catch (YoutubePlaylistException $exception) {
            $this->assertSame('invalidPlaylistUrl', $exception->reason);
            $this->assertSame('This is not a YouTube playlist link.', $exception->userMessage());
        }

        Http::assertNothingSent();
    }

    public function test_missing_api_key_throws_without_calling_the_api(): void
    {
        config(['services.youtube.api_key' => '']);
        Http::fake();

        try {
            $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200);
            $this->fail('Expected YoutubePlaylistException.');
        } catch (YoutubePlaylistException $exception) {
            $this->assertSame('missingApiKey', $exception->reason);
        }

        Http::assertNothingSent();
    }

    public function test_not_found_playlist_throws(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->errorResponse(404, 'playlistNotFound'), 404)]);

        try {
            $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200);
            $this->fail('Expected YoutubePlaylistException.');
        } catch (YoutubePlaylistException $exception) {
            $this->assertSame('playlistNotFound', $exception->reason);
            $this->assertFalse($exception->isQuotaExceeded());
            $this->assertStringContainsString('Playlist not found', $exception->userMessage());
            $this->assertStringNotContainsString(self::API_KEY, $exception->getMessage());
        }

        $call = YoutubeApiCall::query()->sole();
        $this->assertSame(YoutubeApiCallStatus::Failed, $call->status);
        $this->assertSame(1, $call->quota_units);
    }

    public function test_private_playlist_throws(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->errorResponse(403, 'playlistItemsNotAccessible'), 403)]);

        try {
            $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200);
            $this->fail('Expected YoutubePlaylistException.');
        } catch (YoutubePlaylistException $exception) {
            $this->assertStringContainsString('private', $exception->userMessage());
        }
    }

    public function test_quota_exceeded_sets_the_shared_flag_and_records_no_units(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->errorResponse(403, 'quotaExceeded'), 403)]);

        try {
            $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200);
            $this->fail('Expected YoutubePlaylistException.');
        } catch (YoutubePlaylistException $exception) {
            $this->assertTrue($exception->isQuotaExceeded());
            $this->assertStringContainsString('quota', $exception->userMessage());
        }

        $this->assertTrue(app(YoutubeSearchService::class)->isQuotaExceeded());
        $call = YoutubeApiCall::query()->sole();
        $this->assertSame(YoutubeApiCallStatus::QuotaExceeded, $call->status);
        $this->assertSame(0, $call->quota_units);

        rescue(fn () => $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200), report: false);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_throws(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        try {
            $this->youtubePlaylist->videos(self::PLAYLIST_URL, 200);
            $this->fail('Expected YoutubePlaylistException.');
        } catch (YoutubePlaylistException $exception) {
            $this->assertSame('connectionFailed', $exception->reason);
        }

        $this->assertSame(0, YoutubeApiCall::query()->sole()->quota_units);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function page(array $items, int $total, ?string $nextPageToken = null): array
    {
        return array_filter([
            'kind' => 'youtube#playlistItemListResponse',
            'nextPageToken' => $nextPageToken,
            'pageInfo' => ['totalResults' => $total, 'resultsPerPage' => 50],
            'items' => $items,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $videoId, string $title, ?string $videoOwnerChannelTitle, int $position, string $privacyStatus = 'public', string $description = ''): array
    {
        return [
            'kind' => 'youtube#playlistItem',
            'snippet' => array_filter([
                'title' => $title,
                'description' => $description,
                'channelTitle' => 'Playlist Owner',
                'videoOwnerChannelTitle' => $videoOwnerChannelTitle,
                'position' => $position,
                'resourceId' => ['kind' => 'youtube#video', 'videoId' => $videoId],
            ], fn (mixed $value): bool => $value !== null),
            'status' => ['privacyStatus' => $privacyStatus],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(int $code, string $reason): array
    {
        return ['error' => ['code' => $code, 'message' => 'Error', 'errors' => [['reason' => $reason]]]];
    }
}
