<?php

namespace Tests\Feature\Playlists;

use App\Models\Playlist;
use App\Models\Track;
use App\Services\LibraryService;
use App\Services\PlaylistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlaylistService $playlists;

    protected function setUp(): void
    {
        parent::setUp();

        $this->playlists = app(PlaylistService::class);
    }

    public function test_tracks_are_appended_in_order_and_tracks_already_there_are_skipped(): void
    {
        $playlist = Playlist::factory()->create();
        [$a, $b, $c] = Track::factory()->count(3)->create()->all();

        $this->assertSame(['added' => 2, 'skipped' => 0], $this->playlists->addTracks($playlist, [$b->id, $a->id]));
        $this->assertSame(['added' => 1, 'skipped' => 2], $this->playlists->addTracks($playlist, [$a->id, $c->id, 999999]));

        $this->assertOrder($playlist, [$b, $a, $c]);
    }

    public function test_a_track_moves_to_a_zero_based_position(): void
    {
        $playlist = Playlist::factory()->create();
        [$a, $b, $c, $d] = Track::factory()->count(4)->create()->all();
        $this->playlists->addTracks($playlist, [$a->id, $b->id, $c->id, $d->id]);

        $this->playlists->moveTrack($playlist, $d->id, 0);
        $this->assertOrder($playlist, [$d, $a, $b, $c]);

        $this->playlists->moveTrack($playlist, $d->id, 10);
        $this->assertOrder($playlist, [$a, $b, $c, $d]);

        $this->playlists->moveTrack($playlist, $a->id, 2);
        $this->assertOrder($playlist, [$b, $c, $a, $d]);
    }

    public function test_apply_order_saves_the_given_order(): void
    {
        $playlist = Playlist::factory()->create();
        [$a, $b, $c] = Track::factory()->count(3)->create()->all();
        $this->playlists->addTracks($playlist, [$a->id, $b->id, $c->id]);

        $this->playlists->applyOrder($playlist, [$c->id, $a->id]);

        $this->assertOrder($playlist, [$c, $a, $b]);
    }

    public function test_removing_a_track_renumbers_the_rest_and_keeps_it_in_the_library(): void
    {
        $playlist = Playlist::factory()->create();
        [$a, $b, $c] = Track::factory()->count(3)->create()->all();
        $this->playlists->addTracks($playlist, [$a->id, $b->id, $c->id]);

        $this->playlists->removeTrack($playlist, $b->id);

        $this->assertOrder($playlist, [$a, $c]);
        $this->assertModelExists($b);
    }

    public function test_deleting_a_track_or_a_playlist_removes_only_the_playlist_rows(): void
    {
        $playlist = Playlist::factory()->create();
        [$a, $b] = Track::factory()->count(2)->create()->all();
        $this->playlists->addTracks($playlist, [$a->id, $b->id]);

        app(LibraryService::class)->deleteTracks([$a->id]);
        $this->assertSame([$b->id], $playlist->tracks()->pluck('tracks.id')->all());

        $playlist->delete();
        $this->assertModelExists($b);
        $this->assertSame(0, $b->playlists()->count());
    }

    public function test_merging_moves_playlist_places_to_the_kept_track(): void
    {
        $existing = Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black and White']);
        $current = Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black & White Remix']);
        $other = Track::factory()->create();
        $onlyCurrent = Playlist::factory()->create();
        $both = Playlist::factory()->create();
        $this->playlists->addTracks($onlyCurrent, [$other->id, $current->id]);
        $this->playlists->addTracks($both, [$current->id, $other->id, $existing->id]);

        app(LibraryService::class)->mergeTracks($current, $existing, ['title' => 'Black & White']);

        $this->assertOrder($onlyCurrent, [$other, $existing]);
        $this->assertOrder($both, [$other, $existing]);
    }

    /**
     * @param  list<Track>  $expected
     */
    private function assertOrder(Playlist $playlist, array $expected): void
    {
        $rows = $playlist->tracks()->get();

        $this->assertSame(array_map(fn (Track $track): int => $track->id, $expected), $rows->modelKeys());
        $this->assertSame(range(1, count($expected)), $rows->pluck('pivot.position')->all());
    }
}
