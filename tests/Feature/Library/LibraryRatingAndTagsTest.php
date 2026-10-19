<?php

namespace Tests\Feature\Library;

use App\Livewire\Library\Index;
use App\Models\Tag;
use App\Models\Track;
use App\Services\LibraryService;
use App\Services\TagService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryRatingAndTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicking_a_star_sets_the_rating_and_the_same_star_clears_it(): void
    {
        $track = Track::factory()->create();

        $component = Livewire::test(Index::class)->call('rate', $track->id, 4);

        $this->assertSame(4, $track->fresh()->rating);

        $component->call('rate', $track->id, 2);
        $this->assertSame(2, $track->fresh()->rating);

        $component->call('rate', $track->id, 2)->call('rate', $track->id, 9);
        $this->assertNull($track->fresh()->rating);
    }

    public function test_minimum_rating_filter_and_rating_sort(): void
    {
        Track::factory()->create(['title' => 'Three stars', 'rating' => 3]);
        Track::factory()->create(['title' => 'Five stars', 'rating' => 5]);
        Track::factory()->create(['title' => 'Unrated', 'rating' => null]);

        Livewire::test(Index::class)
            ->set('minRating', '4')
            ->assertSee('Five stars')->assertDontSee('Three stars')->assertDontSee('Unrated')
            ->call('clearFilters')
            ->call('sortBy', 'rating')->call('sortBy', 'rating')
            ->assertSeeInOrder(['Five stars', 'Three stars', 'Unrated']);
    }

    public function test_tags_are_added_in_the_panel_case_insensitively_and_shown_in_the_row(): void
    {
        $track = Track::factory()->create();
        $other = Track::factory()->create();
        Tag::factory()->create(['name' => 'Opener'])->tracks()->attach($other);

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->set('newTag', '  opener ')
            ->call('addTag')
            ->assertSet('newTag', '')
            ->set('newTag', 'PL')
            ->call('addTag')
            ->assertSee('#Opener')
            ->assertSee('#PL');

        $this->assertSame(2, Tag::count());
        $this->assertSame(['Opener', 'PL'], $track->tags()->pluck('name')->all());
    }

    public function test_an_empty_or_too_long_tag_is_rejected(): void
    {
        $track = Track::factory()->create();

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->set('newTag', '')
            ->call('addTag')
            ->assertHasErrors('newTag')
            ->set('newTag', str_repeat('x', TagService::MAX_LENGTH + 1))
            ->call('addTag')
            ->assertHasErrors('newTag');

        $this->assertSame(0, Tag::count());
    }

    public function test_removing_the_last_use_of_a_tag_deletes_it_and_drops_it_from_the_filter(): void
    {
        $track = Track::factory()->create();
        $tag = Tag::factory()->create(['name' => 'PL']);
        $tag->tracks()->attach($track);

        Livewire::test(Index::class)
            ->call('addTagFilter', (string) $tag->id)
            ->assertSet('tagFilter', [$tag->id])
            ->call('toggleTrack', $track->id)
            ->call('removeTag', $tag->id)
            ->assertSet('tagFilter', []);

        $this->assertModelMissing($tag);
    }

    public function test_selected_tracks_can_be_tagged_and_untagged(): void
    {
        $first = Track::factory()->create();
        $second = Track::factory()->create();
        $untouched = Track::factory()->create();

        $component = Livewire::test(Index::class)
            ->set('selected', [(string) $first->id, (string) $second->id])
            ->set('bulkTag', 'Lata 80')
            ->call('tagSelected')
            ->assertSet('notice', 'Tagged 2 tracks with “Lata 80”.')
            ->assertSet('bulkTag', '');

        $tag = Tag::query()->sole();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $tag->tracks()->pluck('tracks.id')->all());
        $this->assertSame(0, $untouched->tags()->count());

        $component->set('bulkTag', 'lata 80')
            ->call('untagSelected')
            ->assertSet('notice', 'Removed “Lata 80” from 2 tracks.');

        $this->assertModelMissing($tag);

        $component->set('bulkTag', 'nope')->call('untagSelected')->assertHasErrors('bulkTag');
    }

    public function test_tag_filter_requires_all_chosen_tags(): void
    {
        $pl = Tag::factory()->create(['name' => 'PL']);
        $opener = Tag::factory()->create(['name' => 'opener']);
        $both = Track::factory()->create(['title' => 'Both tags']);
        $onlyPl = Track::factory()->create(['title' => 'Only PL']);
        $both->tags()->attach([$pl->id, $opener->id]);
        $onlyPl->tags()->attach($pl);

        Livewire::test(Index::class)
            ->call('addTagFilter', $pl->id)
            ->assertSee('Both tags')->assertSee('Only PL')
            ->call('addTagFilter', $opener->id)
            ->assertSee('Both tags')->assertDontSee('Only PL')
            ->call('removeTagFilter', $opener->id)
            ->assertSee('Only PL');
    }

    public function test_merging_keeps_the_tags_of_both_tracks(): void
    {
        $existing = Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black and White']);
        $current = Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black & White Remix']);
        $pl = Tag::factory()->create(['name' => 'PL']);
        $opener = Tag::factory()->create(['name' => 'opener']);
        $existing->tags()->attach($pl);
        $current->tags()->attach([$pl->id, $opener->id]);

        app(LibraryService::class)->mergeTracks($current, $existing, ['title' => 'Black & White']);

        $this->assertSame(['opener', 'PL'], $existing->tags()->pluck('name')->all());
    }

    public function test_removing_tracks_deletes_tags_no_track_uses(): void
    {
        $track = Track::factory()->create();
        $kept = Track::factory()->create();
        $onlyHere = Tag::factory()->create();
        $shared = Tag::factory()->create();
        $track->tags()->attach([$onlyHere->id, $shared->id]);
        $kept->tags()->attach($shared);

        app(LibraryService::class)->deleteTracks([$track->id]);

        $this->assertModelMissing($onlyHere);
        $this->assertModelExists($shared);
    }

    public function test_tag_names_are_unique_regardless_of_case(): void
    {
        Tag::factory()->create(['name' => 'PL']);

        $this->expectException(QueryException::class);

        Tag::factory()->create(['name' => 'pl']);
    }
}
