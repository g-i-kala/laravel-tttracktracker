<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Livewire\Imports\Review;
use App\Livewire\Imports\Show;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class YoutubeResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_youtube_stores_search_urls_marks_items_and_batch_searched_and_redirects(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $item = ImportItem::factory()->for($batch)->create(['artist' => 'Bicep', 'title' => 'Glue (Original Mix)']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('searchYoutube')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('imports.show', ['batch' => $batch]);

        $item->refresh();

        $this->assertSame('https://www.youtube.com/results?search_query=Bicep+Glue', $item->youtube_search_url);
        $this->assertSame(ImportItemStatus::Searched, $item->status);
        $this->assertSame(ImportBatchStatus::Searched, $batch->refresh()->status);
    }

    public function test_search_youtube_is_blocked_by_an_invalid_field(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $item = ImportItem::factory()->for($batch)->create(['title' => 'Glue']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->set("items.{$item->id}.title", '')
            ->call('searchYoutube')
            ->assertHasErrors("items.{$item->id}.title")
            ->assertNoRedirect();

        $this->assertNull($item->refresh()->youtube_search_url);
        $this->assertSame(ImportBatchStatus::Parsed, $batch->refresh()->status);
    }

    public function test_searching_again_regenerates_urls_and_keeps_exported_statuses(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Exported]);
        $item = ImportItem::factory()->for($batch)->create([
            'artist' => 'Bicep',
            'title' => 'Glue',
            'status' => ImportItemStatus::Exported,
            'youtube_search_url' => 'https://www.youtube.com/results?search_query=old',
        ]);

        Livewire::test(Review::class, ['batch' => $batch])->call('searchYoutube');

        $item->refresh();

        $this->assertSame('https://www.youtube.com/results?search_query=Bicep+Glue', $item->youtube_search_url);
        $this->assertSame(ImportItemStatus::Exported, $item->status);
        $this->assertSame(ImportBatchStatus::Exported, $batch->refresh()->status);
    }

    public function test_search_youtube_button_is_hidden_while_parsing(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Parsing]);

        Livewire::test(Review::class, ['batch' => $batch])
            ->assertDontSeeHtml('wire:click="searchYoutube"');
    }

    public function test_results_page_lists_tracks_with_search_links_in_a_new_tab(): void
    {
        $batch = ImportBatch::factory()->create(['name' => 'Techno October', 'status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($batch)->create([
            'line_number' => 2,
            'artist' => 'Bicep',
            'title' => 'Glue',
            'youtube_search_url' => 'https://www.youtube.com/results?search_query=Bicep+Glue',
        ]);
        ImportItem::factory()->for($batch)->withMetadata()->create([
            'line_number' => 1,
            'artist' => 'Adam Beyer',
            'title' => 'Teach Me',
            'youtube_search_url' => null,
        ]);

        $this->get(route('imports.show', $batch))
            ->assertOk()
            ->assertSeeLivewire(Show::class)
            ->assertSee('Techno October')
            ->assertSeeInOrder(['Teach Me', 'Glue'])
            ->assertSee('128 BPM · 9A · tszr')
            ->assertSee('href="https://www.youtube.com/results?search_query=Bicep+Glue"', false)
            ->assertSee('href="https://www.youtube.com/results?search_query=Adam+Beyer+Teach+Me"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_results_route_only_matches_numeric_ids(): void
    {
        $this->get('/imports/create')->assertOk()->assertDontSee('YouTube Results');
        $this->get('/imports/abc')->assertNotFound();
    }

    public function test_imports_list_continues_on_results_once_searched(): void
    {
        $searched = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $reviewed = ImportBatch::factory()->create(['status' => ImportBatchStatus::Reviewed]);

        $this->get(route('imports.index'))
            ->assertOk()
            ->assertSee('href="'.route('imports.show', $searched).'"', false)
            ->assertSee('href="'.route('imports.review', $reviewed).'"', false);
    }
}
