<?php

namespace Tests\Feature\Youtube;

use App\Models\YoutubeSearchCache;
use App\Services\YoutubeSearchException;
use App\Services\YoutubeSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YoutubeSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string API_KEY = 'test-youtube-key';

    private YoutubeSearchService $youtubeSearch;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.youtube.api_key' => self::API_KEY, 'services.youtube.cache_ttl_days' => 30]);

        $this->youtubeSearch = app(YoutubeSearchService::class);
    }

    public function test_is_enabled_only_with_api_key(): void
    {
        $this->assertTrue($this->youtubeSearch->isEnabled());

        config(['services.youtube.api_key' => '']);

        $this->assertFalse($this->youtubeSearch->isEnabled());
    }

    public function test_search_calls_the_api_and_maps_results(): void
    {
        Http::fake(['www.googleapis.com/youtube/v3/search*' => Http::response($this->apiResponse())]);

        $results = $this->youtubeSearch->search('Daft Punk  Around the World');

        $this->assertSame([
            [
                'video_id' => 'K0HSD_i2DvA',
                'url' => 'https://www.youtube.com/watch?v=K0HSD_i2DvA',
                'title' => "Daft Punk - Around The World (Official Music Video) & 'Remastered'",
                'channel_title' => 'Daft Punk',
                'description' => null,
                'published_at' => '2009-02-26T19:20:31Z',
            ],
            [
                'video_id' => 'dwDns8x3Jb4',
                'url' => 'https://www.youtube.com/watch?v=dwDns8x3Jb4',
                'title' => 'Daft Punk - Around the World (Official Audio)',
                'channel_title' => 'Daft Punk',
                'description' => 'Listen now',
                'published_at' => null,
            ],
        ], $results);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Goog-Api-Key', self::API_KEY)
            && ! str_contains($request->url(), self::API_KEY)
            && $request['q'] === 'Daft Punk Around the World'
            && $request['type'] === 'video'
            && $request['part'] === 'snippet'
            && (int) $request['maxResults'] === YoutubeSearchService::MAX_RESULTS);
    }

    public function test_search_stores_results_in_cache(): void
    {
        $this->freezeSecond();
        Http::fake(['www.googleapis.com/*' => Http::response($this->apiResponse())]);

        $results = $this->youtubeSearch->search('Daft Punk Around the World');

        $cache = YoutubeSearchCache::query()->sole();
        $this->assertSame('Daft Punk Around the World', $cache->query);
        $this->assertSame($this->youtubeSearch->queryHash('Daft Punk Around the World'), $cache->query_hash);
        $this->assertSame(YoutubeSearchService::PROVIDER, $cache->provider);
        $this->assertEquals($results, $cache->results);
        $this->assertTrue($cache->expires_at->equalTo(now()->addDays(30)));
    }

    public function test_search_uses_valid_cache_without_calling_the_api(): void
    {
        Http::fake();
        $results = [['video_id' => 'abc', 'url' => 'https://www.youtube.com/watch?v=abc', 'title' => 'Cached', 'channel_title' => null, 'description' => null, 'published_at' => null]];
        YoutubeSearchCache::factory()->create([
            'query' => 'Bicep Glue',
            'query_hash' => $this->youtubeSearch->queryHash('Bicep Glue'),
            'results' => $results,
        ]);

        $this->assertEquals($results, $this->youtubeSearch->search('  bicep   GLUE '));
        Http::assertNothingSent();
    }

    public function test_cache_works_without_api_key(): void
    {
        config(['services.youtube.api_key' => '']);
        YoutubeSearchCache::factory()->create([
            'query_hash' => $this->youtubeSearch->queryHash('Bicep Glue'),
            'results' => [],
        ]);

        $this->assertSame([], $this->youtubeSearch->search('Bicep Glue'));
    }

    public function test_expired_cache_is_refreshed(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->apiResponse())]);
        YoutubeSearchCache::factory()->expired()->create([
            'query_hash' => $this->youtubeSearch->queryHash('Daft Punk Around the World'),
            'results' => [],
        ]);

        $results = $this->youtubeSearch->search('Daft Punk Around the World');

        $this->assertCount(2, $results);
        $cache = YoutubeSearchCache::query()->sole();
        $this->assertCount(2, $cache->results);
        $this->assertTrue($cache->expires_at->isFuture());
    }

    public function test_quota_exceeded_throws_quota_exception_and_caches_nothing(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->errorResponse(403, 'quotaExceeded'), 403)]);

        try {
            $this->youtubeSearch->search('Bicep Glue');
            $this->fail('Expected YoutubeSearchException.');
        } catch (YoutubeSearchException $exception) {
            $this->assertTrue($exception->isQuotaExceeded());
            $this->assertSame('quotaExceeded', $exception->reason);
            $this->assertSame('YouTube Data API quota exceeded.', $exception->getMessage());
        }

        $this->assertDatabaseCount('youtube_search_cache', 0);
    }

    public function test_other_api_errors_throw_without_quota_flag(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->errorResponse(400, 'keyInvalid'), 400)]);

        try {
            $this->youtubeSearch->search('Bicep Glue');
            $this->fail('Expected YoutubeSearchException.');
        } catch (YoutubeSearchException $exception) {
            $this->assertFalse($exception->isQuotaExceeded());
            $this->assertSame('keyInvalid', $exception->reason);
            $this->assertStringNotContainsString(self::API_KEY, $exception->getMessage());
        }
    }

    public function test_connection_failure_throws(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        $this->expectException(YoutubeSearchException::class);
        $this->expectExceptionMessage('Could not connect to the YouTube Data API.');

        $this->youtubeSearch->search('Bicep Glue');
    }

    public function test_search_without_api_key_and_cache_throws(): void
    {
        config(['services.youtube.api_key' => '']);
        Http::fake();

        $this->expectException(YoutubeSearchException::class);

        try {
            $this->youtubeSearch->search('Bicep Glue');
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function apiResponse(): array
    {
        return [
            'kind' => 'youtube#searchListResponse',
            'items' => [
                [
                    'id' => ['kind' => 'youtube#video', 'videoId' => 'K0HSD_i2DvA'],
                    'snippet' => [
                        'publishedAt' => '2009-02-26T19:20:31Z',
                        'title' => 'Daft Punk - Around The World (Official Music Video) &amp; &#39;Remastered&#39;',
                        'description' => '',
                        'channelTitle' => 'Daft Punk',
                    ],
                ],
                [
                    'id' => ['kind' => 'youtube#video', 'videoId' => 'dwDns8x3Jb4'],
                    'snippet' => [
                        'title' => 'Daft Punk - Around the World (Official Audio)',
                        'description' => 'Listen now',
                        'channelTitle' => 'Daft Punk',
                    ],
                ],
                [
                    'id' => ['kind' => 'youtube#channel', 'channelId' => 'UC123'],
                    'snippet' => ['title' => 'Not a video'],
                ],
            ],
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
