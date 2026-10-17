<?php

namespace Tests\Feature\Exports;

use App\Livewire\Exports\Index;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_exports_newest_first_with_import_version_links_and_download(): void
    {
        $batch = ImportBatch::factory()->create(['name' => 'Techno October']);
        $first = ExportFile::factory()->for($batch)->create([
            'filename' => 'techno-october_youtube_links.txt',
            'version' => 1,
            'items_count' => 12,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $second = ExportFile::factory()->for($batch)->create([
            'filename' => 'techno-october_youtube_links_v2.txt',
            'version' => 2,
            'items_count' => 14,
        ]);

        $this->get(route('exports.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class)
            ->assertSeeInOrder(['techno-october_youtube_links_v2.txt', 'Latest', 'Techno October', 'v2', '14', 'techno-october_youtube_links.txt', 'v1', '12'])
            ->assertSee('href="'.route('exports.download', $second).'"', false)
            ->assertSee('href="'.route('exports.download', $first).'"', false)
            ->assertSee('href="'.route('imports.show', $batch).'"', false);
    }

    public function test_only_the_highest_version_of_each_import_is_marked_latest(): void
    {
        $batch = ImportBatch::factory()->create();
        ExportFile::factory()->for($batch)->create(['version' => 1]);
        ExportFile::factory()->for($batch)->create(['version' => 2]);
        ExportFile::factory()->create(['version' => 1]);

        $response = $this->get(route('exports.index'))->assertOk();

        $this->assertSame(2, substr_count($response->getContent(), '>Latest</span>'));
    }

    public function test_page_shows_empty_state_without_exports(): void
    {
        $this->get(route('exports.index'))
            ->assertOk()
            ->assertSee('No exports yet');
    }
}
