<?php

namespace Tests\Feature\Library;

use App\Livewire\Imports\Review;
use App\Livewire\Imports\Show;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddToLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.providers.openai.key' => null, 'services.youtube.api_key' => '']);
    }

    public function test_the_modal_preselects_the_tracks_not_in_the_library(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $first = $this->item($batch, 1, 'Lady Pank', 'Mniej niż zero');
        $linked = $this->item($batch, 2, 'Kombi', 'Słodkiego miłego życia');
        $linked->track()->associate(Track::factory()->create())->save();
        $third = $this->item($batch, 3, 'Maanam', 'Kocham cię, kochanie moje');

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->assertSet('showLibraryModal', true)
            ->assertSet('librarySelection', [$first->id, $third->id])
            ->assertSee('In library');
    }

    public function test_adding_creates_tracks_links_the_items_and_shows_a_notice(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $this->item($batch, 1, 'Lady Pank', 'Mniej niż zero');
        $this->item($batch, 2, 'Kombi', 'Słodkiego miłego życia');
        Track::factory()->create(['artist' => 'Kombi', 'title' => 'Słodkiego miłego życia']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->call('addToLibrary')
            ->assertSet('showLibraryModal', false)
            ->assertSet('notice', 'Added 2 tracks to the library: 1 new, 1 linked to tracks already there.')
            ->assertSee('2 in library');

        $this->assertSame(2, Track::count());
        $this->assertSame(0, $batch->items()->whereNull('track_id')->count());
    }

    public function test_only_the_selected_tracks_of_this_import_are_added(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $selected = $this->item($batch, 1, 'Lady Pank', 'Mniej niż zero');
        $this->item($batch, 2, 'Kombi', 'Słodkiego miłego życia');
        $otherImport = ImportItem::factory()->create();

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->set('librarySelection', [(string) $selected->id, (string) $otherImport->id])
            ->call('addToLibrary');

        $this->assertSame(1, Track::count());
        $this->assertNotNull($selected->fresh()->track_id);
        $this->assertNull($otherImport->fresh()->track_id);
    }

    public function test_an_empty_selection_shows_an_error(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $this->item($batch, 1, 'Lady Pank', 'Mniej niż zero');

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->set('librarySelection', [])
            ->call('addToLibrary')
            ->assertHasErrors('librarySelection')
            ->assertSet('showLibraryModal', true);

        $this->assertSame(0, Track::count());
    }

    public function test_mark_as_wanted_marks_the_added_tracks(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $this->item($batch, 1, 'Lady Pank', 'Mniej niż zero');

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->set('markAsWanted', true)
            ->call('addToLibrary');

        $this->assertTrue(Track::sole()->wanted);
    }

    public function test_a_possible_match_asks_and_defaults_to_a_new_track(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $item = $this->item($batch, 1, 'Bicep', 'Glue (Extended Mix)');
        $original = Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->call('addToLibrary')
            ->assertSet('libraryChoosingSimilar', true)
            ->assertSet('similarChoices', [$item->id => 'new'])
            ->assertSee('Same track as')
            ->call('addToLibrary')
            ->assertSet('showLibraryModal', false);

        $this->assertSame(2, Track::count());
        $this->assertFalse($item->fresh()->track->is($original));
    }

    public function test_choosing_same_track_links_the_item_to_the_existing_track(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $item = $this->item($batch, 1, 'Bob Moses & Bicep', 'Glue');
        $existing = Track::factory()->create(['artist' => 'Bicep & Bob Moses', 'title' => 'Glue']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->call('addToLibrary')
            ->set("similarChoices.{$item->id}", (string) $existing->id)
            ->call('addToLibrary')
            ->assertSet('notice', 'Added 1 track to the library: 0 new, 1 linked to tracks already there.');

        $this->assertSame(1, Track::count());
        $this->assertTrue($item->fresh()->track->is($existing));
    }

    public function test_a_same_track_choice_outside_the_matches_is_ignored(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $item = $this->item($batch, 1, 'Bicep', 'Glue (Extended Mix)');
        Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $unrelated = Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black and White']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->call('addToLibrary')
            ->set("similarChoices.{$item->id}", (string) $unrelated->id)
            ->call('addToLibrary');

        $this->assertFalse($item->fresh()->track->is($unrelated));
        $this->assertSame(3, Track::count());
    }

    public function test_back_returns_to_the_selection_step(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        $this->item($batch, 1, 'Bicep', 'Glue (Extended Mix)');
        Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('openLibraryModal')
            ->call('addToLibrary')
            ->call('backToLibrarySelection')
            ->assertSet('libraryChoosingSimilar', false)
            ->assertSee('Mark as wanted');
    }

    public function test_youtube_results_adds_tracks_with_their_links(): void
    {
        $batch = ImportBatch::factory()->create(['status' => 'searched']);
        $item = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 1]);

        Livewire::test(Show::class, ['batch' => $batch])
            ->assertSee('Add to library')
            ->call('openLibraryModal')
            ->call('addToLibrary')
            ->assertSee('1 in library');

        $track = Track::sole();
        $this->assertSame($item->selected_youtube_url, $track->youtube_url);
        $this->assertTrue($track->isPlayableOnline());
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
