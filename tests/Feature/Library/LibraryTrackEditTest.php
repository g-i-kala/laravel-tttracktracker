<?php

namespace Tests\Feature\Library;

use App\Livewire\Library\Index;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryTrackEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicking_a_row_opens_the_track_and_clicking_again_closes_it(): void
    {
        $track = Track::factory()->withMetadata()->create(['artist' => 'Kombi', 'title' => 'Black and White', 'release_year' => 1981]);

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->assertSet('expandedTrackId', $track->id)
            ->assertSet('edit.artist', 'Kombi')
            ->assertSet('edit.bpm', '128')
            ->assertSet('edit.release_year', '1981')
            ->assertSet('edit.playable_offline', false)
            ->assertSee('Sources')
            ->call('toggleTrack', $track->id)
            ->assertSet('expandedTrackId', null)
            ->assertDontSee('Sources');
    }

    public function test_fields_are_saved_and_keys_recomputed(): void
    {
        $track = Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black and White']);

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->set('edit.title', '  Black  and White (Extended Mix) ')
            ->set('edit.musical_key', 'Bbm')
            ->set('edit.bpm', '118')
            ->set('edit.release_year', '1981')
            ->set('edit.genre', '')
            ->set('edit.notes', 'Opener')
            ->assertHasNoErrors()
            ->assertDispatched('track-saved', id: $track->id);

        $track->refresh();
        $this->assertSame('Black and White (Extended Mix)', $track->title);
        $this->assertSame('kombi|black and white extended mix', $track->normalized_key);
        $this->assertSame('kombi|black and white', $track->similar_key);
        $this->assertSame('3A', $track->camelot_key);
        $this->assertSame(118, $track->bpm);
        $this->assertSame(1981, $track->release_year);
        $this->assertNull($track->genre);
        $this->assertSame('Opener', $track->notes);
    }

    public function test_invalid_values_are_not_saved(): void
    {
        $track = Track::factory()->create(['title' => 'Glue', 'bpm' => 120]);

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->set('edit.title', '')
            ->assertHasErrors('edit.title')
            ->set('edit.bpm', '999')
            ->assertHasErrors('edit.bpm')
            ->set('edit.release_year', '1850')
            ->assertHasErrors('edit.release_year')
            ->set('edit.youtube_url', 'https://vimeo.com/123')
            ->assertHasErrors('edit.youtube_url');

        $track->refresh();
        $this->assertSame('Glue', $track->title);
        $this->assertSame(120, $track->bpm);
        $this->assertNull($track->youtube_url);
    }

    public function test_a_youtube_link_is_normalized_and_can_be_removed(): void
    {
        $track = Track::factory()->create();

        $component = Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->set('edit.youtube_url', 'https://youtu.be/dQw4w9WgXcQ?t=10')
            ->assertSet('edit.youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertTrue($track->fresh()->isPlayableOnline());

        $component->set('edit.youtube_url', '');

        $this->assertNull($track->fresh()->youtube_url);
    }

    public function test_pof_clears_wanted_and_disables_it(): void
    {
        $track = Track::factory()->wanted()->create();

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->set('edit.playable_offline', true)
            ->assertSet('edit.wanted', false);

        $this->assertTrue($track->fresh()->playable_offline);
        $this->assertFalse($track->fresh()->wanted);
    }

    public function test_the_row_pof_button_keeps_the_open_panel_in_step(): void
    {
        $track = Track::factory()->wanted()->create();

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->call('togglePlayableOffline', $track->id)
            ->assertSet('edit.playable_offline', true)
            ->assertSet('edit.wanted', false);
    }

    public function test_an_artist_and_title_of_another_track_are_not_saved(): void
    {
        Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black and White']);
        $track = Track::factory()->create(['artist' => 'Kombi', 'title' => 'Black & White Remix']);

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->set('edit.title', 'Black & White')
            ->assertHasErrors('edit.title')
            ->assertSee('Already in the library as “Kombi - Black and White”');

        $this->assertSame('Black & White Remix', $track->fresh()->title);
    }

    public function test_sources_list_the_imports_the_track_came_from(): void
    {
        $track = Track::factory()->create();
        $batch = ImportBatch::factory()->parsed()->create(['name' => 'Lata 80 PL']);
        ImportItem::factory()->for($batch)->for($track)->create(['line_number' => 4, 'artist' => 'Kombi', 'title' => 'Black and White']);

        Livewire::test(Index::class)
            ->call('toggleTrack', $track->id)
            ->assertSee('Lata 80 PL')
            ->assertSee('#4 Kombi - Black and White')
            ->assertSee(route('imports.review', $batch));
    }
}
