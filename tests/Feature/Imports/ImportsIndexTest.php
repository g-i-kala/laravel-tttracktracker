<?php

namespace Tests\Feature\Imports;

use App\Livewire\Imports\Index;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_batches_newest_first_with_track_count_and_status(): void
    {
        $older = ImportBatch::factory()->reviewed()->create(['name' => 'September set', 'created_at' => now()->subDay()]);
        $newer = ImportBatch::factory()->parsed()->create(['name' => 'October set']);
        ImportItem::factory()->count(3)->for($newer)->create();

        $this->get(route('imports.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class)
            ->assertSeeInOrder(['October set', '3', 'To review', 'September set', '0', 'Reviewed'])
            ->assertSee(route('imports.review', $older));
    }

    public function test_page_shows_empty_state_without_imports(): void
    {
        $this->get(route('imports.index'))
            ->assertOk()
            ->assertSee('No imports yet');
    }
}
