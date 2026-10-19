<?php

namespace Tests\Feature\Library;

use App\Models\ImportItem;
use App\Models\Track;
use App\Services\LibraryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryServiceTest extends TestCase
{
    use RefreshDatabase;

    private LibraryService $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->library = app(LibraryService::class);
    }

    public function test_adding_an_item_creates_a_track_with_its_fields_and_links_the_item(): void
    {
        $item = ImportItem::factory()->withMetadata()->withSelectedYoutubeLink()->create([
            'artist' => 'Adam Beyer',
            'title' => 'Teach Me (Original Mix)',
        ]);

        $track = $this->library->addItem($item);

        $this->assertSame('Adam Beyer', $track->artist);
        $this->assertSame('Teach Me (Original Mix)', $track->title);
        $this->assertSame('adam beyer|teach me', $track->normalized_key);
        $this->assertSame('adam beyer|teach me', $track->similar_key);
        $this->assertSame('Deep House', $track->genre);
        $this->assertSame(128, $track->bpm);
        $this->assertSame('E Minor', $track->musical_key);
        $this->assertSame('9A', $track->camelot_key);
        $this->assertSame('tszr', $track->label);
        $this->assertSame($item->selected_youtube_url, $track->youtube_url);
        $this->assertTrue($track->isPlayableOnline());
        $this->assertFalse($track->playable_offline);
        $this->assertFalse($track->wanted);
        $this->assertTrue($item->fresh()->track->is($track));
    }

    public function test_a_track_from_an_item_without_a_link_is_not_playable_online(): void
    {
        $track = $this->library->addItem(ImportItem::factory()->create());

        $this->assertNull($track->youtube_url);
        $this->assertFalse($track->isPlayableOnline());
    }

    public function test_mark_as_wanted_marks_a_new_track_as_wanted(): void
    {
        $track = $this->library->addItem(ImportItem::factory()->create(), markAsWanted: true);

        $this->assertTrue($track->fresh()->wanted);
    }

    public function test_mark_as_wanted_marks_an_existing_track_but_not_one_playable_offline(): void
    {
        $existing = Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $owned = Track::factory()->playableOffline()->create(['artist' => 'Bicep', 'title' => 'Atlas']);

        $this->library->addItem(ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']), markAsWanted: true);
        $this->library->addItem(ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Atlas']), markAsWanted: true);

        $this->assertTrue($existing->fresh()->wanted);
        $this->assertFalse($owned->fresh()->wanted);
    }

    public function test_adding_without_mark_as_wanted_keeps_a_wanted_track_wanted(): void
    {
        $track = Track::factory()->wanted()->create(['artist' => 'Bicep', 'title' => 'Glue']);

        $this->library->addItem(ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']));

        $this->assertTrue($track->fresh()->wanted);
    }

    public function test_a_track_that_becomes_playable_offline_is_no_longer_wanted(): void
    {
        $track = Track::factory()->wanted()->create();

        $track->update(['playable_offline' => true]);

        $this->assertFalse($track->fresh()->wanted);
    }

    public function test_an_exact_match_links_to_the_existing_track_and_fills_only_its_empty_fields(): void
    {
        $track = Track::factory()->create([
            'artist' => 'Adam Beyer',
            'title' => 'Teach Me',
            'genre' => 'Techno',
        ]);
        $item = ImportItem::factory()->withMetadata()->withSelectedYoutubeLink()->create([
            'artist' => 'adam beyer',
            'title' => 'Teach Me (Original Mix)',
        ]);

        $linked = $this->library->addItem($item);

        $this->assertTrue($linked->is($track));
        $this->assertSame(1, Track::count());
        $track->refresh();
        $this->assertSame('Adam Beyer', $track->artist);
        $this->assertSame('Teach Me', $track->title);
        $this->assertSame('Techno', $track->genre);
        $this->assertSame(128, $track->bpm);
        $this->assertSame('9A', $track->camelot_key);
        $this->assertSame($item->selected_youtube_url, $track->youtube_url);
        $this->assertFalse($track->wanted);
        $this->assertTrue($item->fresh()->track->is($track));
    }

    public function test_items_from_many_imports_link_to_one_track(): void
    {
        $first = ImportItem::factory()->create(['artist' => 'Lady Pank', 'title' => 'Mniej niż zero']);
        $second = ImportItem::factory()->create(['artist' => 'LADY PANK', 'title' => 'Mniej niz zero']);

        $track = $this->library->addItem($first);
        $this->library->addItem($second);

        $this->assertSame(1, Track::count());
        $this->assertCount(2, $track->importItems);
    }

    public function test_another_version_creates_a_new_track_and_is_listed_as_similar(): void
    {
        $original = Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $item = ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Glue (Extended Mix)']);

        $this->assertNull($this->library->exactTrack($item));
        $this->assertSame([$original->id], $this->library->similarTracks($item)->modelKeys());

        $track = $this->library->addItem($item);

        $this->assertFalse($track->is($original));
        $this->assertSame(2, Track::count());
    }

    public function test_similar_tracks_include_swapped_artists_but_not_the_exact_track_or_other_tracks(): void
    {
        $swapped = Track::factory()->create(['artist' => 'Bob Moses & Bicep', 'title' => 'Glue']);
        Track::factory()->create(['artist' => 'Bicep & Bob Moses', 'title' => 'Glue']);
        Track::factory()->create(['artist' => 'Bicep', 'title' => 'Atlas']);
        $item = ImportItem::factory()->create(['artist' => 'Bicep & Bob Moses', 'title' => 'Glue']);

        $this->assertSame([$swapped->id], $this->library->similarTracks($item)->modelKeys());
    }

    public function test_the_same_track_choice_links_the_item_to_the_similar_track(): void
    {
        $track = Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $item = ImportItem::factory()->withMetadata()->create(['artist' => 'Bicep', 'title' => 'Glue (Radio Edit)']);

        $this->library->linkToTrack($item, $track);

        $this->assertSame(1, Track::count());
        $this->assertSame('Glue', $track->fresh()->title);
        $this->assertSame(128, $track->fresh()->bpm);
        $this->assertTrue($item->fresh()->track->is($track));
    }

    public function test_deleting_a_track_unlinks_its_import_items(): void
    {
        $item = ImportItem::factory()->create();
        $track = $this->library->addItem($item);

        $track->delete();

        $this->assertNull($item->fresh()->track_id);
    }

    public function test_an_item_already_in_the_library_stays_on_its_track_after_an_edit(): void
    {
        $item = ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $track = $this->library->addItem($item);
        $item->update(['title' => 'Glue (Radio Edit)', 'normalized_key' => 'bicep|glue radio edit']);

        $this->assertTrue($this->library->addItem($item->fresh(), markAsWanted: true)->is($track));
        $this->assertSame(1, Track::count());
        $this->assertTrue($track->fresh()->wanted);
    }

    public function test_add_items_counts_new_and_linked_tracks_and_applies_same_track_choices(): void
    {
        $existing = Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $chosen = Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black and White']);
        $exact = ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Glue', 'line_number' => 1]);
        $sameAsChosen = ImportItem::factory()->create(['artist' => 'Kombi', 'title' => 'Black & White (Remastered)', 'line_number' => 2]);
        $new = ImportItem::factory()->create(['artist' => 'Maanam', 'title' => 'Cykady na Cykladach', 'line_number' => 3]);

        $result = $this->library->addItems(new Collection([$new, $exact, $sameAsChosen]), sameTrackIds: [$sameAsChosen->id => $chosen->id]);

        $this->assertSame(['created' => 1, 'linked' => 2], $result);
        $this->assertTrue($exact->fresh()->track->is($existing));
        $this->assertTrue($sameAsChosen->fresh()->track->is($chosen));
        $this->assertSame(3, Track::count());
    }

    public function test_similar_matches_skip_items_in_the_library_or_with_an_exact_match(): void
    {
        $glue = Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $glueExtended = Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue (Extended Mix)']);
        $radioEdit = ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Glue (Radio Edit)', 'line_number' => 1]);
        $exact = ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Glue', 'line_number' => 2]);
        $linked = ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Glue (Dub)', 'line_number' => 3]);
        $linked->track()->associate($glue)->save();
        $unrelated = ImportItem::factory()->create(['artist' => 'Bicep', 'title' => 'Atlas', 'line_number' => 4]);

        $matches = $this->library->similarMatches(new Collection([$radioEdit, $exact, $linked->fresh(), $unrelated]));

        $this->assertSame([$radioEdit->id], array_keys($matches));
        $this->assertSame([$glue->id, $glueExtended->id], $matches[$radioEdit->id]->modelKeys());
    }
}
