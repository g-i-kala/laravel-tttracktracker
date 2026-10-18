<?php

namespace Tests\Feature\Imports;

use App\Ai\Agents\PlaylistTitleParser;
use App\Enums\ImportBatchStatus;
use App\Jobs\ParsePlaylistTitlesWithAiJob;
use App\Livewire\Imports\Create;
use App\Livewire\Imports\Review;
use App\Models\ImportBatch;
use App\Services\ImportBatchService;
use App\Services\ImportParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PlaylistImportTest extends TestCase
{
    use RefreshDatabase;

    private const array ATTRIBUTES = ['name' => '80s party', 'year' => null, 'month' => null, 'default_genre' => '80s'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.providers.openai.key' => null]);
    }

    public function test_playlist_import_without_ai_stores_parsed_items_with_their_links(): void
    {
        Queue::fake();

        $batch = app(ImportBatchService::class)->createFromPlaylist(self::ATTRIBUTES, $this->videos());

        $this->assertSame(ImportBatchStatus::Parsed, $batch->refresh()->status);
        $this->assertSame(ImportBatchService::SOURCE_YOUTUBE_PLAYLIST, $batch->source_type);
        $this->assertSame("a-ha - Take On Me (Official Video)\nTake On Me\nTen Ostatni Dzień", $batch->raw_input);
        Queue::assertNothingPushed();

        $items = $batch->items()->orderBy('line_number')->get();
        $this->assertSame([1, 2, 3], $items->pluck('line_number')->all());
        $this->assertSame(['a-ha', 'Kombi', null], $items->pluck('artist')->all());
        $this->assertSame(['Take On Me', 'Take On Me', 'Ten Ostatni Dzień'], $items->pluck('title')->all());
        $this->assertSame(['80s', '80s', '80s'], $items->pluck('genre')->all());
        $this->assertSame([true, true, true], $items->pluck('include_in_export')->all());
        $this->assertSame('https://www.youtube.com/watch?v=djV11Xbc914', $items[0]->selected_youtube_url);
        $this->assertSame('djV11Xbc914', $items[0]->selected_youtube_video_id);
        $this->assertSame('a-ha - Take On Me (Official Video)', $items[0]->selected_youtube_title);
        $this->assertSame('a-ha', $items[0]->selected_youtube_channel);
        $this->assertSame(ImportParserService::ARTIST_NOT_DETECTED, $items[2]->error_message);
    }

    public function test_playlist_import_with_ai_queues_title_parsing(): void
    {
        config(['ai.providers.openai.key' => 'test-key']);
        Queue::fake();

        $batch = app(ImportBatchService::class)->createFromPlaylist(self::ATTRIBUTES, $this->videos());

        $this->assertSame(ImportBatchStatus::Parsing, $batch->refresh()->status);
        $this->assertSame(3, $batch->items()->count());
        Queue::assertPushed(ParsePlaylistTitlesWithAiJob::class, fn (ParsePlaylistTitlesWithAiJob $job): bool => $job->batch->is($batch));
    }

    public function test_playlist_of_topic_videos_needs_no_ai(): void
    {
        config(['ai.providers.openai.key' => 'test-key']);
        Queue::fake();

        $batch = app(ImportBatchService::class)->createFromPlaylist(self::ATTRIBUTES, [$this->videos()[1]]);

        $this->assertSame(ImportBatchStatus::Parsed, $batch->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_ai_job_updates_non_topic_items_by_number(): void
    {
        $batch = app(ImportBatchService::class)->createFromPlaylist(self::ATTRIBUTES, $this->videos());
        $batch->update(['status' => ImportBatchStatus::Parsing]);
        config(['ai.providers.openai.key' => 'test-key']);

        PlaylistTitleParser::fake([['tracks' => [
            ['number' => 3, 'artist' => 'Perfect', 'title' => 'Ten Ostatni Dzień'],
            ['number' => 1, 'artist' => 'a-ha', 'title' => 'Take On Me'],
            ['number' => 2, 'artist' => 'Wrong', 'title' => 'Topic item not sent'],
            ['number' => 9, 'artist' => 'Nobody', 'title' => 'Unknown number'],
        ]]])->preventStrayPrompts();

        ParsePlaylistTitlesWithAiJob::dispatchSync($batch);

        PlaylistTitleParser::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('1. a-ha - Take On Me (Official Video) [channel: a-ha]')
            && $prompt->contains('3. Ten Ostatni Dzień [channel: Polskie Nagrania]')
            && ! $prompt->contains('Kombi - Topic'));

        $this->assertSame(ImportBatchStatus::Parsed, $batch->refresh()->status);
        $items = $batch->items()->orderBy('line_number')->get();
        $this->assertSame(['a-ha', 'Kombi', 'Perfect'], $items->pluck('artist')->all());
        $this->assertSame('perfect|ten ostatni dzien', $items[2]->normalized_key);
        $this->assertNull($items[2]->error_message);
        $this->assertSame('https://www.youtube.com/watch?v=perfect0001', $items[2]->selected_youtube_url);
    }

    public function test_ai_job_does_nothing_when_the_batch_is_no_longer_parsing(): void
    {
        PlaylistTitleParser::fake()->preventStrayPrompts();
        $batch = ImportBatch::factory()->parsed()->create();

        ParsePlaylistTitlesWithAiJob::dispatchSync($batch);

        PlaylistTitleParser::assertNeverPrompted();
    }

    public function test_failed_ai_job_keeps_the_simple_parser_result(): void
    {
        Queue::fake();
        $batch = app(ImportBatchService::class)->createFromPlaylist(self::ATTRIBUTES, $this->videos());
        $batch->update(['status' => ImportBatchStatus::Parsing]);

        (new ParsePlaylistTitlesWithAiJob($batch))->failed(new RuntimeException('Timeout'));

        $this->assertSame(ImportBatchStatus::Parsed, $batch->refresh()->status);
        $this->assertSame(['a-ha', 'Kombi', null], $batch->items()->orderBy('line_number')->pluck('artist')->all());
    }

    public function test_search_youtube_with_a_key_spends_no_search_on_playlist_items(): void
    {
        config(['services.youtube.api_key' => 'test-youtube-key']);
        Queue::fake();
        Bus::fake();
        $batch = app(ImportBatchService::class)->createFromPlaylist(self::ATTRIBUTES, $this->videos());

        app(ImportBatchService::class)->searchYoutube($batch);

        Bus::assertNothingBatched();
        $this->assertSame(ImportBatchStatus::Searched, $batch->refresh()->status);
        $this->assertSame(3, $batch->items()->where('include_in_export', true)->count());
    }

    public function test_new_import_shows_the_playlist_tab(): void
    {
        config(['services.youtube.api_key' => 'test-youtube-key']);

        Livewire::test(Create::class)
            ->assertSee('Paste list')
            ->assertSee('YouTube playlist')
            ->assertDontSee('Playlist link')
            ->call('showSource', 'playlist')
            ->assertSet('source', 'playlist')
            ->assertSee('Playlist link')
            ->assertSee('Import playlist')
            ->assertSeeHtml('data-testid="playlist-hint"')
            ->assertDontSee('Track list');

        Livewire::withQueryParams(['source' => 'playlist'])->test(Create::class)->assertSet('source', 'playlist');
        Livewire::withQueryParams(['source' => 'nope'])->test(Create::class)->assertSet('source', 'paste');
    }

    public function test_playlist_tab_without_youtube_key_explains_and_blocks_the_import(): void
    {
        Http::fake();

        Livewire::test(Create::class)
            ->call('showSource', 'playlist')
            ->assertSeeHtml('data-testid="playlist-no-key"')
            ->set('playlistUrl', 'https://www.youtube.com/playlist?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG')
            ->call('save')
            ->assertHasErrors('playlistUrl');

        Http::assertNothingSent();
        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_playlist_link_is_validated_before_calling_the_api(): void
    {
        config(['services.youtube.api_key' => 'test-youtube-key']);
        Http::fake();

        Livewire::test(Create::class)
            ->set('source', 'playlist')
            ->set('playlistUrl', 'https://www.youtube.com/watch?v=djV11Xbc914')
            ->call('save')
            ->assertHasErrors('playlistUrl')
            ->assertSee('This is not a YouTube playlist link.');

        Http::assertNothingSent();
    }

    public function test_playlist_import_creates_the_batch_and_reports_skipped_videos(): void
    {
        config(['services.youtube.api_key' => 'test-youtube-key']);
        Queue::fake();
        Http::fake(['www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
            'pageInfo' => ['totalResults' => 3],
            'items' => [
                $this->apiItem('djV11Xbc914', 'a-ha - Take On Me (Official Video)', 'a-ha', 'public'),
                $this->apiItem('xxxxxxxxxx1', 'Deleted video', null, 'privacyStatusUnspecified'),
                $this->apiItem('kombi000001', 'Black and White', 'Kombi - Topic', 'unlisted'),
            ],
        ])]);

        Livewire::test(Create::class)
            ->set('name', '80s party')
            ->set('defaultGenre', '80s')
            ->set('source', 'playlist')
            ->set('playlistUrl', 'https://www.youtube.com/playlist?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('imports.review', ImportBatch::sole()));

        $batch = ImportBatch::sole();
        $this->assertSame('80s party', $batch->name);
        $this->assertSame(ImportBatchService::SOURCE_YOUTUBE_PLAYLIST, $batch->source_type);
        $this->assertSame(['a-ha', 'Kombi'], $batch->items()->orderBy('line_number')->pluck('artist')->all());
        $this->assertSame('Imported 2 tracks from the playlist, each with its video as the link. Skipped 1 deleted or private video.', session('status'));
        $this->assertTrue(session('check_duplicates'));
    }

    public function test_playlist_import_mentions_videos_over_the_limit(): void
    {
        config(['services.youtube.api_key' => 'test-youtube-key']);
        Queue::fake();
        Http::fake(['www.googleapis.com/*' => Http::response([
            'pageInfo' => ['totalResults' => 260],
            'nextPageToken' => 'NEXT',
            'items' => collect(range(1, 50))->map(fn (int $number): array => $this->apiItem(sprintf('video%06d', $number), "Artist {$number} - Title {$number}", 'Channel', 'public'))->all(),
        ])]);

        Livewire::test(Create::class)
            ->set('source', 'playlist')
            ->set('playlistUrl', 'PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(200, ImportBatch::sole()->items()->count());
        $this->assertStringContainsString('Only the first 200 videos are imported; 60 more were left out.', session('status'));
    }

    public function test_playlist_errors_are_shown_on_the_field(): void
    {
        config(['services.youtube.api_key' => 'test-youtube-key']);
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['code' => 404, 'errors' => [['reason' => 'playlistNotFound']]]], 404)]);

        Livewire::test(Create::class)
            ->set('source', 'playlist')
            ->set('playlistUrl', 'PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG')
            ->call('save')
            ->assertHasErrors('playlistUrl')
            ->assertSee('Playlist not found');

        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_playlist_without_playable_videos_is_not_imported(): void
    {
        config(['services.youtube.api_key' => 'test-youtube-key']);
        Http::fake(['www.googleapis.com/*' => Http::response(['pageInfo' => ['totalResults' => 1], 'items' => [
            $this->apiItem('xxxxxxxxxx1', 'Private video', null, 'private'),
        ]])]);

        Livewire::test(Create::class)
            ->set('source', 'playlist')
            ->set('playlistUrl', 'PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG')
            ->call('save')
            ->assertHasErrors('playlistUrl')
            ->assertSee('This playlist has no playable videos.');

        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_review_explains_ai_title_cleanup_for_playlist_imports(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Parsing, 'source_type' => ImportBatchService::SOURCE_YOUTUBE_PLAYLIST]);

        Livewire::test(Review::class, ['batch' => $batch])
            ->assertSee('Cleaning up the video titles with AI');
    }

    /**
     * @return array<string, mixed>
     */
    private function apiItem(string $videoId, string $title, ?string $channel, string $privacyStatus): array
    {
        return [
            'snippet' => array_filter([
                'title' => $title,
                'videoOwnerChannelTitle' => $channel,
                'resourceId' => ['videoId' => $videoId],
            ]),
            'status' => ['privacyStatus' => $privacyStatus],
        ];
    }

    /**
     * @return list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, position: int}>
     */
    private function videos(): array
    {
        return [
            ['video_id' => 'djV11Xbc914', 'url' => 'https://www.youtube.com/watch?v=djV11Xbc914', 'title' => 'a-ha - Take On Me (Official Video)', 'channel_title' => 'a-ha', 'description' => null, 'position' => 0],
            ['video_id' => 'kombi000001', 'url' => 'https://www.youtube.com/watch?v=kombi000001', 'title' => 'Take On Me', 'channel_title' => 'Kombi - Topic', 'description' => 'Provided to YouTube by Polskie Nagrania', 'position' => 1],
            ['video_id' => 'perfect0001', 'url' => 'https://www.youtube.com/watch?v=perfect0001', 'title' => 'Ten Ostatni Dzień', 'channel_title' => 'Polskie Nagrania', 'description' => null, 'position' => 2],
        ];
    }
}
