<?php

namespace Tests\Feature\Youtube;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Jobs\SearchYoutubeForImportItemJob;
use App\Livewire\Imports\Review;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\YoutubeApiCall;
use App\Models\YoutubeCandidate;
use App\Services\ImportItemService;
use App\Services\ImportParserService;
use App\Services\YoutubeScoringService;
use App\Services\YoutubeSearchService;
use App\Services\YoutubeSearchUrlService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class YoutubeApiSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.youtube.api_key' => 'test-youtube-key',
            'services.youtube.auto_include_threshold' => 80,
            'services.youtube.preselect_threshold' => 60,
            'services.youtube.candidates_limit' => 3,
        ]);
    }

    public function test_search_youtube_with_key_queues_one_job_per_item_without_a_link(): void
    {
        Bus::fake();
        $batch = ImportBatch::factory()->parsed()->create();
        $first = ImportItem::factory()->for($batch)->create(['line_number' => 1, 'artist' => 'Bicep', 'title' => 'Glue (Original Mix)']);
        $second = ImportItem::factory()->for($batch)->create(['line_number' => 2]);
        $custom = $this->customLinkItem($batch, 3);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('searchYoutube')
            ->assertRedirectToRoute('imports.show', ['batch' => $batch]);

        Bus::assertBatched(fn (PendingBatch $pending): bool => $pending->jobs->count() === 2
            && $pending->allowsFailures()
            && $pending->jobs->every(fn ($job): bool => $job instanceof SearchYoutubeForImportItemJob)
            && $pending->jobs->pluck('item.id')->all() === [$first->id, $second->id]);

        $this->assertSame(ImportBatchStatus::Searching, $batch->refresh()->status);
        $this->assertSame(ImportItemStatus::SearchPending, $first->refresh()->status);
        $this->assertSame('https://www.youtube.com/results?search_query=Bicep+Glue', $first->youtube_search_url);
        $this->assertSame(ImportItemStatus::Searched, $custom->refresh()->status);
        $this->assertNotNull($custom->youtube_search_url);
    }

    public function test_search_youtube_without_key_stays_in_no_key_mode(): void
    {
        Bus::fake();
        config(['services.youtube.api_key' => '']);
        $batch = ImportBatch::factory()->parsed()->create();
        ImportItem::factory()->for($batch)->create();

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        Bus::assertNothingBatched();
        $this->assertSame(ImportBatchStatus::Searched, $batch->refresh()->status);
    }

    public function test_nothing_is_queued_when_every_item_has_a_link(): void
    {
        Bus::fake();
        $batch = ImportBatch::factory()->parsed()->create();
        $this->customLinkItem($batch, 1);

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        Bus::assertNothingBatched();
        $this->assertSame(ImportBatchStatus::Searched, $batch->refresh()->status);
    }

    public function test_search_stores_candidates_and_preselects_by_score(): void
    {
        Http::fake(fn (Request $request) => Http::response($this->searchResponse(match ($request['q']) {
            'Bicep Glue' => [
                $this->video('glueLive', 'Bicep - Glue (Live)', 'Bicep'),
                $this->video('cooking', 'Cooking pasta', 'Chef'),
                $this->video('glueOfficial', 'Bicep - Glue (Official Audio)', 'Bicep'),
                $this->video('glueFan', 'Bicep - Glue', 'some fan'),
            ],
            'Daft Punk Around the World' => [$this->video('atwFan', 'Daft Punk - Around the World', 'some fan')],
            'Bicep Apricots' => [$this->video('cooking', 'Cooking pasta', 'Chef')],
        })));
        $batch = ImportBatch::factory()->parsed()->create();
        $autoIncluded = ImportItem::factory()->for($batch)->create(['line_number' => 1, 'artist' => 'Bicep', 'title' => 'Glue (Original Mix)']);
        $preselected = ImportItem::factory()->for($batch)->create(['line_number' => 2, 'artist' => 'Daft Punk', 'title' => 'Around the World']);
        $notSelected = ImportItem::factory()->for($batch)->create(['line_number' => 3, 'artist' => 'Bicep', 'title' => 'Apricots']);
        $custom = $this->customLinkItem($batch, 4);

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        $autoIncluded->refresh();
        $this->assertSame(ImportItemStatus::Searched, $autoIncluded->status);
        $this->assertSame('https://www.youtube.com/watch?v=glueOfficial', $autoIncluded->selected_youtube_url);
        $this->assertSame('glueOfficial', $autoIncluded->selected_youtube_video_id);
        $this->assertSame('Bicep - Glue (Official Audio)', $autoIncluded->selected_youtube_title);
        $this->assertSame('Bicep', $autoIncluded->selected_youtube_channel);
        $this->assertSame(90, $autoIncluded->youtube_score);
        $this->assertTrue($autoIncluded->include_in_export);
        $this->assertSame(
            [['glueOfficial', 90, 1], ['glueFan', 70, 2], ['glueLive', 55, 3]],
            $autoIncluded->youtubeCandidates()->orderBy('rank')->get()->map(fn (YoutubeCandidate $candidate): array => [$candidate->video_id, $candidate->score, $candidate->rank])->all(),
        );

        $preselected->refresh();
        $this->assertSame('atwFan', $preselected->selected_youtube_video_id);
        $this->assertSame(70, $preselected->youtube_score);
        $this->assertFalse($preselected->include_in_export);

        $notSelected->refresh();
        $this->assertSame(ImportItemStatus::Searched, $notSelected->status);
        $this->assertNull($notSelected->selected_youtube_url);
        $this->assertFalse($notSelected->include_in_export);
        $this->assertSame(1, $notSelected->youtubeCandidates()->count());

        $this->assertSame('https://www.youtube.com/watch?v=customLink4', $custom->refresh()->selected_youtube_url);
        $this->assertSame(0, $custom->youtubeCandidates()->count());

        $this->assertSame(ImportBatchStatus::Searched, $batch->refresh()->status);
        Http::assertSentCount(3);
        $this->assertSame(3, YoutubeApiCall::query()->where('import_batch_id', $batch->id)->count());
    }

    public function test_searching_again_replaces_old_candidates(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->searchResponse([$this->video('glueOfficial', 'Bicep - Glue (Official Audio)', 'Bicep')]))]);
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $item = ImportItem::factory()->for($batch)->create(['artist' => 'Bicep', 'title' => 'Glue', 'status' => ImportItemStatus::Searched]);
        YoutubeCandidate::factory()->for($item)->count(3)->create();

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        $this->assertSame(['glueOfficial'], $item->youtubeCandidates()->pluck('video_id')->all());
    }

    public function test_quota_exceeded_falls_back_for_this_and_all_remaining_items(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403)]);
        $batch = ImportBatch::factory()->parsed()->create();
        $items = ImportItem::factory()->for($batch)->count(3)->sequence(fn ($sequence) => ['line_number' => $sequence->index + 1])->create();

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        Http::assertSentCount(1);
        $this->assertTrue(app(YoutubeSearchService::class)->isQuotaExceeded());

        foreach ($items as $item) {
            $item->refresh();
            $this->assertSame(ImportItemStatus::Searched, $item->status);
            $this->assertSame(ImportItemService::QUOTA_EXCEEDED_NOTICE, $item->error_message);
            $this->assertNotNull($item->youtube_search_url);
            $this->assertNull($item->selected_youtube_url);
        }

        $this->assertSame(ImportBatchStatus::Searched, $batch->refresh()->status);
    }

    public function test_api_error_falls_back_for_that_item_only(): void
    {
        Http::fake(fn (Request $request) => $request['q'] === 'Bicep Glue'
            ? Http::response(['error' => ['code' => 500, 'errors' => [['reason' => 'backendError']]]], 500)
            : Http::response($this->searchResponse([$this->video('atwOfficial', 'Daft Punk - Around the World (Official Audio)', 'Daft Punk')])));
        $batch = ImportBatch::factory()->parsed()->create();
        $failed = ImportItem::factory()->for($batch)->create(['line_number' => 1, 'artist' => 'Bicep', 'title' => 'Glue']);
        $found = ImportItem::factory()->for($batch)->create(['line_number' => 2, 'artist' => 'Daft Punk', 'title' => 'Around the World']);

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        $this->assertSame(ImportItemService::SEARCH_FAILED_NOTICE, $failed->refresh()->error_message);
        $this->assertSame(ImportItemStatus::Searched, $failed->status);
        $this->assertSame('atwOfficial', $found->refresh()->selected_youtube_video_id);
        $this->assertNull($found->error_message);
        $this->assertFalse(app(YoutubeSearchService::class)->isQuotaExceeded());
    }

    public function test_missing_artist_notice_is_kept_after_a_successful_search(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->searchResponse([]))]);
        $batch = ImportBatch::factory()->parsed()->create();
        $item = ImportItem::factory()->for($batch)->create(['artist' => null, 'title' => 'Teach Me', 'error_message' => ImportParserService::ARTIST_NOT_DETECTED]);

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        $this->assertSame(ImportParserService::ARTIST_NOT_DETECTED, $item->refresh()->error_message);
        Http::assertSent(fn (Request $request): bool => $request['q'] === 'Teach Me');
    }

    public function test_exported_batch_returns_to_exported_after_the_search(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response($this->searchResponse([]))]);
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Exported]);
        ExportFile::factory()->for($batch)->create();
        ImportItem::factory()->for($batch)->create(['status' => ImportItemStatus::Searched]);

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        $this->assertSame(ImportBatchStatus::Exported, $batch->refresh()->status);
    }

    public function test_job_skips_an_item_that_is_not_pending(): void
    {
        Http::fake();
        $item = ImportItem::factory()->create(['status' => ImportItemStatus::Searched]);

        (new SearchYoutubeForImportItemJob($item))->handle(
            app(YoutubeSearchService::class),
            app(YoutubeSearchUrlService::class),
            app(YoutubeScoringService::class),
            app(ImportItemService::class),
        );

        Http::assertNothingSent();
        $this->assertSame(0, $item->youtubeCandidates()->count());
    }

    public function test_job_failure_falls_back_to_the_search_link(): void
    {
        $item = ImportItem::factory()->create(['status' => ImportItemStatus::SearchPending]);

        (new SearchYoutubeForImportItemJob($item))->failed(new \RuntimeException('Boom'));

        $this->assertSame(ImportItemStatus::Searched, $item->refresh()->status);
        $this->assertSame(ImportItemService::SEARCH_FAILED_NOTICE, $item->error_message);
    }

    public function test_searching_batch_continues_on_youtube_results(): void
    {
        $this->assertTrue(ImportBatchStatus::Searching->hasSearchResults());
        $this->assertSame('imports.show', ImportBatchStatus::Searching->screenRouteName());
    }

    private function customLinkItem(ImportBatch $batch, int $lineNumber): ImportItem
    {
        return ImportItem::factory()->for($batch)->create([
            'line_number' => $lineNumber,
            'selected_youtube_url' => "https://www.youtube.com/watch?v=customLink{$lineNumber}",
            'selected_youtube_video_id' => "customLink{$lineNumber}",
            'youtube_score' => null,
            'include_in_export' => true,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $videos
     * @return array<string, mixed>
     */
    private function searchResponse(array $videos): array
    {
        return ['kind' => 'youtube#searchListResponse', 'items' => $videos];
    }

    /**
     * @return array<string, mixed>
     */
    private function video(string $videoId, string $title, string $channel): array
    {
        return [
            'id' => ['kind' => 'youtube#video', 'videoId' => $videoId],
            'snippet' => ['title' => $title, 'channelTitle' => $channel, 'description' => ''],
        ];
    }
}
