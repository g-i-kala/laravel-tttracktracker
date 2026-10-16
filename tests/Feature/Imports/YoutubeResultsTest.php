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

    public function test_imports_list_links_to_review_and_to_youtube_links_once_searched(): void
    {
        $searched = ImportBatch::factory()->create(['name' => 'Searched batch', 'status' => ImportBatchStatus::Searched]);
        $reviewed = ImportBatch::factory()->create(['name' => 'Reviewed batch', 'status' => ImportBatchStatus::Reviewed]);

        $this->get(route('imports.index'))
            ->assertOk()
            ->assertSeeHtml('<a href="'.route('imports.review', $searched).'" wire:navigate class="text-xs font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Review</a>')
            ->assertSeeHtml('<a href="'.route('imports.show', $searched).'" wire:navigate class="ml-3 text-xs font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">YouTube links</a>')
            ->assertSeeHtml('<a href="'.route('imports.review', $reviewed).'" wire:navigate class="text-xs font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Review</a>')
            ->assertDontSeeHtml('href="'.route('imports.show', $reviewed).'"');
    }

    public function test_review_links_to_youtube_links_without_searching_again_once_searched(): void
    {
        $searched = ImportItem::factory()->for(ImportBatch::factory()->state(['status' => ImportBatchStatus::Searched]))->create()->importBatch;
        $parsed = ImportItem::factory()->for(ImportBatch::factory()->parsed())->create()->importBatch;

        Livewire::test(Review::class, ['batch' => $searched])
            ->assertSeeHtml('href="'.route('imports.show', $searched).'"')
            ->assertSee('YouTube links');

        Livewire::test(Review::class, ['batch' => $parsed])
            ->assertDontSeeHtml('href="'.route('imports.show', $parsed).'"');
    }

    public function test_pasting_a_link_normalizes_it_selects_it_and_includes_the_item(): void
    {
        $item = ImportItem::factory()->for(ImportBatch::factory()->state(['status' => ImportBatchStatus::Searched]))->create();

        Livewire::test(Show::class, ['batch' => $item->importBatch])
            ->set("links.{$item->id}", 'https://youtu.be/dQw4w9WgXcQ?si=abc')
            ->assertHasNoErrors()
            ->assertSet("links.{$item->id}", 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertDispatched('item-saved', id: $item->id)
            ->assertSee('1 with a link · 1 in export');

        $item->refresh();

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $item->selected_youtube_url);
        $this->assertSame('dQw4w9WgXcQ', $item->selected_youtube_video_id);
        $this->assertTrue($item->include_in_export);
    }

    public function test_replacing_an_api_candidate_with_a_custom_link_clears_its_title_channel_and_score(): void
    {
        $item = ImportItem::factory()->withSelectedYoutubeLink()->create();

        Livewire::test(Show::class, ['batch' => $item->importBatch])
            ->set("links.{$item->id}", 'https://www.youtube.com/shorts/dQw4w9WgXcQ');

        $item->refresh();

        $this->assertSame('dQw4w9WgXcQ', $item->selected_youtube_video_id);
        $this->assertNull($item->selected_youtube_title);
        $this->assertNull($item->selected_youtube_channel);
        $this->assertNull($item->youtube_score);
    }

    public function test_an_invalid_link_shows_an_error_and_keeps_the_saved_link(): void
    {
        $item = ImportItem::factory()->withSelectedYoutubeLink()->create();
        $savedUrl = $item->selected_youtube_url;

        Livewire::test(Show::class, ['batch' => $item->importBatch])
            ->set("links.{$item->id}", 'https://vimeo.com/123456789')
            ->assertHasErrors("links.{$item->id}")
            ->assertSee(Show::INVALID_LINK_MESSAGE)
            ->assertNotDispatched('item-saved');

        $this->assertSame($savedUrl, $item->refresh()->selected_youtube_url);
        $this->assertTrue($item->include_in_export);
    }

    public function test_clearing_the_link_removes_it_and_excludes_the_item(): void
    {
        $item = ImportItem::factory()->withSelectedYoutubeLink()->create();

        Livewire::test(Show::class, ['batch' => $item->importBatch])
            ->assertSet("links.{$item->id}", $item->selected_youtube_url)
            ->set("links.{$item->id}", ' ')
            ->assertHasNoErrors();

        $item->refresh();

        $this->assertNull($item->selected_youtube_url);
        $this->assertNull($item->selected_youtube_video_id);
        $this->assertFalse($item->include_in_export);
    }

    public function test_include_in_export_can_be_toggled_only_with_a_link(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $withLink = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create();
        $withoutLink = ImportItem::factory()->for($batch)->create();

        $component = Livewire::test(Show::class, ['batch' => $batch])
            ->call('toggleExport', $withLink->id)
            ->call('toggleExport', $withoutLink->id);

        $this->assertFalse($withLink->refresh()->include_in_export);
        $this->assertFalse($withoutLink->refresh()->include_in_export);

        $component->call('toggleExport', $withLink->id);

        $this->assertTrue($withLink->refresh()->include_in_export);
    }

    public function test_items_of_another_batch_cannot_be_changed_on_results(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $foreignItem = ImportItem::factory()->withSelectedYoutubeLink()->create();

        Livewire::test(Show::class, ['batch' => $batch])
            ->call('toggleExport', $foreignItem->id)
            ->assertNotFound();

        Livewire::test(Show::class, ['batch' => $batch])
            ->set("links.{$foreignItem->id}", '')
            ->assertNotFound();

        $this->assertTrue($foreignItem->refresh()->include_in_export);
        $this->assertNotNull($foreignItem->selected_youtube_url);
    }

    public function test_link_field_sends_its_value_to_the_server_on_blur(): void
    {
        $item = ImportItem::factory()->create();

        Livewire::test(Show::class, ['batch' => $item->importBatch])
            ->assertSeeHtml("wire:model.live.blur=\"links.{$item->id}\"")
            ->assertDontSeeHtml('wire:model.blur=');
    }
}
