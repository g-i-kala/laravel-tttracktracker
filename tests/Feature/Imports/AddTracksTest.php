<?php

namespace Tests\Feature\Imports;

use App\Ai\Agents\TrackListParser;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Jobs\SearchYoutubeForImportItemJob;
use App\Livewire\Imports\Review;
use App\Livewire\Imports\Show;
use App\Models\AiUsageLog;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\ImportBatchService;
use App\Services\ImportParserService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AddTracksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.providers.openai.key' => null, 'services.youtube.api_key' => '']);
    }

    public function test_append_continues_line_numbers_and_skips_tracks_already_in_the_batch(): void
    {
        $batch = ImportBatch::factory()->create(['default_genre' => 'House']);
        $this->item($batch, 3, 'Bicep', 'Glue (Original Mix)');
        $parser = app(ImportParserService::class);

        $result = app(ImportBatchService::class)->appendParsedLines($batch, $parser->parse(
            "Bicep - Glue\nDaft Punk - Around the World\nDaft Punk - Around The World\nBonobo - Cirrus"
        ));

        $this->assertSame(['added' => 2, 'skipped' => 2], $result);
        $this->assertSame(
            [[3, 'Glue (Original Mix)'], [4, 'Around the World'], [5, 'Cirrus']],
            $batch->items()->orderBy('line_number')->get()->map(fn (ImportItem $item): array => [$item->line_number, $item->title])->all(),
        );
        $added = $batch->items()->where('line_number', 4)->sole();
        $this->assertSame('House', $added->genre);
        $this->assertSame(ImportItemStatus::Parsed, $added->status);
    }

    public function test_review_adds_tracks_with_the_line_parser_and_shows_a_notice(): void
    {
        $batch = ImportBatch::factory()->reviewed()->create();
        $this->item($batch, 1, 'Bicep', 'Glue');

        Livewire::test(Review::class, ['batch' => $batch])
            ->set('showAddTracksModal', true)
            ->set('addTracksInput', "Bicep - Glue\nBonobo - Cirrus")
            ->call('addTracks')
            ->assertHasNoErrors()
            ->assertSet('showAddTracksModal', false)
            ->assertSet('addTracksInput', '')
            ->assertSet('notice', 'Added 1 track. 1 track already in this import was skipped.')
            ->assertSee('Cirrus');

        $this->assertSame(2, $batch->items()->count());
        $this->assertSame(ImportBatchStatus::Reviewed, $batch->refresh()->status);
    }

    public function test_add_tracks_validates_the_input(): void
    {
        $batch = ImportBatch::factory()->reviewed()->create();
        ImportItem::factory()->for($batch)->count(199)->sequence(fn ($sequence) => ['line_number' => $sequence->index + 1])->create();

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('addTracks')
            ->assertHasErrors(['addTracksInput' => 'required'])
            ->set('addTracksInput', "Zed - One\nZed - Two")
            ->call('addTracks')
            ->assertSee('Too many tracks: the import would have 201.');

        $this->assertSame(199, $batch->items()->count());
    }

    public function test_add_tracks_uses_ai_and_logs_usage_against_the_batch(): void
    {
        config(['ai.providers.openai.key' => 'test-key']);
        TrackListParser::fake([['tracks' => [
            ['artist' => 'Midas Field, Marijn Jansen', 'title' => 'What We Got (Original Mix)', 'genre' => 'Deep House', 'bpm' => 128, 'key' => 'E Minor', 'label' => 'tszr', 'source' => 'What We Got'],
        ]]])->preventStrayPrompts();
        $batch = ImportBatch::factory()->reviewed()->create();

        Livewire::test(Review::class, ['batch' => $batch])
            ->set('addTracksInput', "What We Got\nOriginal Mix\nMidas Field, Marijn Jansen")
            ->call('addTracks')
            ->assertHasNoErrors();

        $item = $batch->items()->sole();
        $this->assertSame('Midas Field, Marijn Jansen', $item->artist);
        $this->assertSame('9A', $item->camelot_key);
        $this->assertSame($batch->id, AiUsageLog::query()->sole()->import_batch_id);
    }

    public function test_no_detected_tracks_shows_an_error(): void
    {
        config(['ai.providers.openai.key' => 'test-key']);
        TrackListParser::fake([['tracks' => []]])->preventStrayPrompts();
        $batch = ImportBatch::factory()->reviewed()->create();

        Livewire::test(Review::class, ['batch' => $batch])
            ->set('addTracksInput', 'just some text')
            ->call('addTracks')
            ->assertHasErrors('addTracksInput')
            ->assertSee('No tracks detected.');
    }

    public function test_ai_failure_offers_the_simple_parser(): void
    {
        config(['ai.providers.openai.key' => 'test-key']);
        TrackListParser::fake(fn () => throw new RuntimeException('Request timed out.'));
        $batch = ImportBatch::factory()->reviewed()->create();

        $component = Livewire::test(Review::class, ['batch' => $batch])
            ->set('addTracksInput', 'Bonobo - Cirrus')
            ->call('addTracks')
            ->assertHasErrors('addTracksInput')
            ->assertSet('addTracksAiFailed', true)
            ->assertSee('Use simple parser');

        $this->assertSame(0, $batch->items()->count());

        $component->call('addTracks', true)->assertHasNoErrors();

        $this->assertSame('Cirrus', $batch->items()->sole()->title);
    }

    public function test_results_screen_searches_new_tracks_right_away_in_api_mode(): void
    {
        Bus::fake();
        config(['services.youtube.api_key' => 'test-youtube-key']);
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $this->item($batch, 1, 'Bicep', 'Glue')->update([
            'status' => ImportItemStatus::Searched,
            'selected_youtube_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
            'selected_youtube_video_id' => 'aaaaaaaaaaa',
            'include_in_export' => true,
        ]);

        $component = Livewire::test(Show::class, ['batch' => $batch])
            ->set('addTracksInput', 'Bonobo - Cirrus')
            ->call('addTracks')
            ->assertHasNoErrors()
            ->assertSet('notice', 'Added 1 track.')
            ->assertSee('Searching YouTube…');

        $newItem = $batch->items()->where('title', 'Cirrus')->sole();
        Bus::assertBatched(fn (PendingBatch $pending): bool => $pending->jobs->count() === 1
            && $pending->jobs->first() instanceof SearchYoutubeForImportItemJob
            && $pending->jobs->first()->item->is($newItem));
        $this->assertSame(ImportBatchStatus::Searching, $batch->refresh()->status);
        $component->assertSet('pendingItemIds', [$newItem->id])
            ->assertSet("links.{$newItem->id}", '');
    }

    public function test_results_screen_adds_search_links_in_no_key_mode(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Exported]);
        $this->item($batch, 1, 'Bicep', 'Glue')->update(['status' => ImportItemStatus::Exported]);

        Livewire::test(Show::class, ['batch' => $batch])
            ->set('addTracksInput', 'Bonobo - Cirrus')
            ->call('addTracks')
            ->assertHasNoErrors();

        $newItem = $batch->items()->where('title', 'Cirrus')->sole();
        $this->assertSame('https://www.youtube.com/results?search_query=Bonobo+Cirrus', $newItem->youtube_search_url);
        $this->assertSame(ImportItemStatus::Searched, $newItem->status);
        $this->assertSame(ImportBatchStatus::Exported, $batch->refresh()->status);
    }

    public function test_add_tracks_button_is_disabled_while_searching(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searching]);

        Livewire::test(Show::class, ['batch' => $batch])
            ->assertSeeHtml('title="Wait until the YouTube search is finished"');
    }

    private function item(ImportBatch $batch, int $lineNumber, string $artist, string $title): ImportItem
    {
        return ImportItem::factory()->for($batch)->create([
            'line_number' => $lineNumber,
            'artist' => $artist,
            'title' => $title,
        ]);
    }
}
