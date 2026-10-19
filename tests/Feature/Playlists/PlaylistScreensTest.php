<?php

namespace Tests\Feature\Playlists;

use App\Livewire\Playlists\Index;
use App\Livewire\Playlists\Show;
use App\Models\Playlist;
use App\Models\Track;
use App\Services\PlaylistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlaylistScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_playlists_with_track_counts_and_an_empty_state(): void
    {
        $this->get(route('playlists.index'))->assertOk()->assertSee('No playlists yet');

        $playlist = Playlist::factory()->create(['name' => '80s party', 'notes' => 'Saturday']);
        app(PlaylistService::class)->addTracks($playlist, Track::factory()->count(2)->create()->modelKeys());

        $this->get(route('playlists.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class)
            ->assertSeeInOrder(['80s party', 'Saturday', '2'])
            ->assertSee(route('playlists.show', $playlist));
    }

    public function test_a_playlist_is_created_and_opened(): void
    {
        Livewire::test(Index::class)
            ->set('name', '')
            ->call('create')
            ->assertHasErrors('name')
            ->set('name', ' 80s party ')
            ->set('notes', 'Saturday')
            ->call('create')
            ->assertRedirect(route('playlists.show', Playlist::sole()));

        $this->assertSame('80s party', Playlist::sole()->name);
    }

    public function test_deleting_a_playlist_keeps_its_tracks(): void
    {
        $playlist = Playlist::factory()->create(['name' => '80s party']);
        $track = Track::factory()->create();
        app(PlaylistService::class)->addTracks($playlist, [$track->id]);

        Livewire::test(Index::class)
            ->call('confirmDelete', $playlist->id)
            ->assertSee('(1 track)')
            ->call('delete')
            ->assertSet('notice', 'Deleted the playlist “80s party”. Its tracks stay in the library.');

        $this->assertModelMissing($playlist);
        $this->assertModelExists($track);
    }

    public function test_the_playlist_screen_lists_tracks_in_order_and_drag_moves_them(): void
    {
        $playlist = Playlist::factory()->create(['name' => '80s party']);
        $a = Track::factory()->create(['title' => 'Alpha']);
        $b = Track::factory()->create(['title' => 'Bravo']);
        app(PlaylistService::class)->addTracks($playlist, [$a->id, $b->id]);

        $this->get(route('playlists.show', $playlist))->assertOk()->assertSee('80s party')->assertSee('wire:sort="moveTrack"', false);

        Livewire::test(Show::class, ['playlist' => $playlist])
            ->assertSeeInOrder(['Alpha', 'Bravo'])
            ->call('moveTrack', (string) $b->id, 0)
            ->assertSeeInOrder(['Bravo', 'Alpha']);
    }

    public function test_sorting_is_a_view_until_apply_this_order(): void
    {
        $playlist = Playlist::factory()->create();
        $fast = Track::factory()->create(['title' => 'Fast', 'bpm' => 140]);
        $slow = Track::factory()->create(['title' => 'Slow', 'bpm' => 95]);
        $none = Track::factory()->create(['title' => 'No tempo', 'bpm' => null]);
        app(PlaylistService::class)->addTracks($playlist, [$fast->id, $slow->id, $none->id]);

        $component = Livewire::test(Show::class, ['playlist' => $playlist])
            ->call('sortBy', 'bpm')
            ->assertSeeInOrder(['Slow', 'Fast', 'No tempo'])
            ->assertSee('Apply this order')
            ->assertDontSee('wire:sort="moveTrack"', false)
            ->call('moveTrack', (string) $none->id, 0);

        $this->assertSame([$fast->id, $slow->id, $none->id], $playlist->tracks()->pluck('tracks.id')->all());

        $component->call('applyOrder')
            ->assertSet('sort', null)
            ->assertSet('notice', 'Saved this order as the playlist order.');

        $this->assertSame([$slow->id, $fast->id, $none->id], $playlist->tracks()->pluck('tracks.id')->all());

        $component->call('sortBy', 'title')->call('manualOrder')->assertSet('sort', null);
    }

    public function test_a_track_is_removed_from_the_playlist(): void
    {
        $playlist = Playlist::factory()->create();
        $track = Track::factory()->create(['title' => 'Alpha']);
        app(PlaylistService::class)->addTracks($playlist, [$track->id]);

        Livewire::test(Show::class, ['playlist' => $playlist])
            ->call('removeTrack', $track->id)
            ->assertSee('No tracks on this playlist yet');

        $this->assertModelExists($track);
    }

    public function test_name_and_notes_are_edited_in_a_modal(): void
    {
        $playlist = Playlist::factory()->create(['name' => 'Old']);

        Livewire::test(Show::class, ['playlist' => $playlist])
            ->call('openEditModal')
            ->assertSet('name', 'Old')
            ->set('name', '')
            ->call('saveDetails')
            ->assertHasErrors('name')
            ->set('name', '80s party')
            ->set('notes', 'Saturday')
            ->call('saveDetails')
            ->assertSet('showEditModal', false)
            ->assertSee('80s party');

        $this->assertSame('Saturday', $playlist->fresh()->notes);
    }
}
