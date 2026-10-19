<?php

namespace Tests\Feature\Library;

use App\Livewire\Library\Index;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_the_empty_state_without_tracks(): void
    {
        $this->get(route('library.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class)
            ->assertSee('No tracks in the library yet');
    }

    public function test_tracks_are_listed_by_artist_then_title_with_availability_badges(): void
    {
        Track::factory()->create(['artist' => 'maanam', 'title' => 'Cykady']);
        Track::factory()->playableOffline()->create(['artist' => 'Kombi', 'title' => 'Black and White', 'camelot_key' => '5A', 'bpm' => 118, 'release_year' => 1981]);
        Track::factory()->withYoutubeLink()->wanted()->create(['artist' => 'Kombi', 'title' => 'Atlantyda']);

        Livewire::test(Index::class)
            ->assertSee('3 tracks')
            ->assertSeeInOrder(['Atlantyda', 'PON', 'Wanted', 'Black and White', '118', '5A', '1981', 'POF', 'Cykady']);
    }

    public function test_search_matches_artist_title_label_and_ignores_accents(): void
    {
        Track::factory()->create(['artist' => 'Lady Pank', 'title' => 'Mniej niż zero']);
        Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black and White', 'label' => 'Polskie Nagrania']);
        Track::factory()->create(['artist' => 'Maanam', 'title' => 'Cykady']);

        $this->assertSearch('niz zero', ['Mniej niż zero']);
        $this->assertSearch('LADY', ['Mniej niż zero']);
        $this->assertSearch('nagrania', ['Black and White']);
        $this->assertSearch('100%', []);
    }

    public function test_availability_filters_combine_and_none_excludes_the_others(): void
    {
        Track::factory()->playableOffline()->create(['title' => 'Offline only']);
        Track::factory()->withYoutubeLink()->wanted()->create(['title' => 'Online and wanted']);
        Track::factory()->withYoutubeLink()->create(['title' => 'Online only']);
        Track::factory()->create(['title' => 'Nothing yet']);

        Livewire::test(Index::class)
            ->call('toggleAvailability', 'pon')
            ->assertSee('Online and wanted')->assertSee('Online only')->assertDontSee('Offline only')
            ->call('toggleAvailability', 'wanted')
            ->assertSet('availability', ['pon', 'wanted'])
            ->assertSee('Online and wanted')->assertDontSee('Online only')
            ->call('toggleAvailability', 'none')
            ->assertSet('availability', ['none'])
            ->assertSee('Nothing yet')->assertDontSee('Online and wanted')->assertDontSee('Offline only')
            ->call('toggleAvailability', 'pof')
            ->assertSet('availability', ['pof'])
            ->assertSee('Offline only')->assertDontSee('Nothing yet')
            ->call('toggleAvailability', 'pof')
            ->assertSet('availability', []);
    }

    public function test_genre_camelot_bpm_and_year_filters(): void
    {
        Track::factory()->create(['title' => 'Synth 1983', 'genre' => 'Synth-pop', 'camelot_key' => '8A', 'bpm' => 120, 'release_year' => 1983]);
        Track::factory()->create(['title' => 'Synth 1991', 'genre' => 'Synth-pop', 'camelot_key' => '10A', 'bpm' => 128, 'release_year' => 1991]);
        Track::factory()->create(['title' => 'Rock 1985', 'genre' => 'Rock', 'camelot_key' => '8A', 'bpm' => 140, 'release_year' => 1985]);

        $component = Livewire::test(Index::class)
            ->assertSeeInOrder(['8A', '10A']);

        $component->set('genre', 'Synth-pop')
            ->assertSee('Synth 1983')->assertSee('Synth 1991')->assertDontSee('Rock 1985')
            ->assertSee('2 of 3 tracks match the filters.')
            ->set('yearFrom', '1980')->set('yearTo', '1989')
            ->assertSee('Synth 1983')->assertDontSee('Synth 1991')
            ->call('clearFilters')
            ->set('camelot', '8A')->set('bpmFrom', '125')
            ->assertSee('Rock 1985')->assertDontSee('Synth 1983')
            ->set('bpmTo', 'abc')
            ->assertSee('Rock 1985');
    }

    public function test_sorting_by_a_column_flips_the_direction_and_keeps_empty_values_last(): void
    {
        Track::factory()->create(['title' => 'Slow', 'bpm' => 95]);
        Track::factory()->create(['title' => 'No tempo', 'bpm' => null]);
        Track::factory()->create(['title' => 'Fast', 'bpm' => 140]);

        Livewire::test(Index::class)
            ->call('sortBy', 'bpm')
            ->assertSet('direction', 'asc')
            ->assertSeeInOrder(['Slow', 'Fast', 'No tempo'])
            ->call('sortBy', 'bpm')
            ->assertSet('direction', 'desc')
            ->assertSeeInOrder(['Fast', 'Slow', 'No tempo'])
            ->call('sortBy', 'unknown')
            ->assertSet('sort', 'bpm');
    }

    public function test_camelot_sorts_in_wheel_order(): void
    {
        Track::factory()->create(['title' => 'Ten', 'camelot_key' => '10A']);
        Track::factory()->create(['title' => 'Two', 'camelot_key' => '2B']);
        Track::factory()->create(['title' => 'Nine', 'camelot_key' => '9A']);

        Livewire::test(Index::class)
            ->call('sortBy', 'camelot')
            ->assertSeeInOrder(['Two', 'Nine', 'Ten']);
    }

    public function test_tracks_are_paginated_fifty_per_page(): void
    {
        Track::factory()->count(Index::PER_PAGE + 1)->create();

        $tracks = Livewire::test(Index::class)->instance()->tracks;

        $this->assertCount(Index::PER_PAGE, $tracks->items());
        $this->assertSame(2, $tracks->lastPage());
    }

    public function test_the_pof_button_toggles_playable_offline_and_clears_wanted(): void
    {
        $track = Track::factory()->wanted()->create();

        $component = Livewire::test(Index::class)->call('togglePlayableOffline', $track->id);

        $track->refresh();
        $this->assertTrue($track->playable_offline);
        $this->assertFalse($track->wanted);

        $component->call('togglePlayableOffline', $track->id);

        $this->assertFalse($track->fresh()->playable_offline);
    }

    public function test_selected_tracks_can_be_marked_and_unmarked_as_pof(): void
    {
        $first = Track::factory()->wanted()->create();
        $second = Track::factory()->create();
        $untouched = Track::factory()->create();

        Livewire::test(Index::class)
            ->set('selected', [(string) $first->id, (string) $second->id])
            ->assertSee('2 selected')
            ->call('setSelectedPlayableOffline', true)
            ->assertSet('selected', [])
            ->assertSet('notice', 'Marked 2 tracks as playable offline (POF).');

        $this->assertTrue($first->fresh()->playable_offline);
        $this->assertFalse($first->fresh()->wanted);
        $this->assertTrue($second->fresh()->playable_offline);
        $this->assertFalse($untouched->fresh()->playable_offline);

        Livewire::test(Index::class)
            ->set('selected', [(string) $first->id])
            ->call('setSelectedPlayableOffline', false)
            ->assertSet('notice', 'Removed POF from 1 track.');

        $this->assertFalse($first->fresh()->playable_offline);
        $this->assertTrue($second->fresh()->playable_offline);
    }

    public function test_select_all_ticks_and_unticks_the_tracks_on_this_page(): void
    {
        $tracks = Track::factory()->count(2)->create();
        $ids = $tracks->pluck('id')->map(fn (int $id): string => (string) $id)->sort()->values()->all();

        $component = Livewire::test(Index::class)->call('toggleAllOnPage');

        $selected = $component->get('selected');
        sort($selected);
        $this->assertSame($ids, $selected);

        $component->call('toggleAllOnPage')->assertSet('selected', []);
    }

    /**
     * @param  list<string>  $expectedTitles
     */
    private function assertSearch(string $search, array $expectedTitles): void
    {
        $titles = Livewire::test(Index::class)
            ->set('search', $search)
            ->instance()
            ->tracks
            ->pluck('title')
            ->all();

        $this->assertSame($expectedTitles, $titles);
    }
}
