<?php

namespace Tests\Feature\Imports;

use App\Livewire\Imports\Index;
use App\Models\AiUsageLog;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\YoutubeCandidate;
use App\Services\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteImportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_deleting_a_batch_removes_candidates_items_exports_and_files_but_keeps_ai_logs(): void
    {
        $batch = ImportBatch::factory()->create();
        $item = ImportItem::factory()->for($batch)->create();
        $candidate = YoutubeCandidate::factory()->for($item)->create();
        $export = ExportFile::factory()->for($batch)->create(['path' => "exports/{$batch->id}/a_youtube_links.txt"]);
        Storage::disk('local')->put($export->path, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $log = AiUsageLog::factory()->create(['import_batch_id' => $batch->id]);
        $otherItem = ImportItem::factory()->create();

        $deleted = app(ImportBatchService::class)->deleteBatches([$batch->id]);

        $this->assertSame(1, $deleted);
        $this->assertModelMissing($batch);
        $this->assertModelMissing($item);
        $this->assertModelMissing($candidate);
        $this->assertModelMissing($export);
        Storage::disk('local')->assertMissing($export->path);
        Storage::disk('local')->assertDirectoryEmpty('exports');
        $this->assertNull($log->refresh()->import_batch_id);
        $this->assertModelExists($otherItem);
    }

    public function test_delete_button_opens_a_modal_with_a_summary_and_deletes_on_confirm(): void
    {
        $batch = ImportBatch::factory()->create(['name' => 'Old test']);
        ImportItem::factory()->for($batch)->count(3)->create();
        ExportFile::factory()->for($batch)->create();

        Livewire::test(Index::class)
            ->call('confirmDelete', $batch->id)
            ->assertSet('showDeleteModal', true)
            ->assertSeeText('Delete “Old test”?')
            ->assertSee('This removes 3 tracks and 1 export file (TXT).')
            ->call('delete')
            ->assertSet('showDeleteModal', false)
            ->assertSee('Deleted 1 import.')
            ->assertSee('No imports yet');

        $this->assertModelMissing($batch);
    }

    public function test_cancelling_the_modal_keeps_the_import(): void
    {
        $batch = ImportBatch::factory()->create();

        Livewire::test(Index::class)
            ->call('confirmDelete', $batch->id)
            ->set('showDeleteModal', false);

        $this->assertModelExists($batch);
    }

    public function test_several_selected_imports_can_be_deleted_at_once(): void
    {
        [$first, $second, $kept] = ImportBatch::factory()->count(3)->create();
        ImportItem::factory()->for($first)->count(2)->create();
        ImportItem::factory()->for($second)->create();

        Livewire::test(Index::class)
            ->set('selected', [(string) $first->id, (string) $second->id])
            ->assertSee('2 selected')
            ->call('confirmDelete')
            ->assertSee('Delete 2 imports?')
            ->assertSee('This removes 3 tracks and 0 export files (TXT).')
            ->call('delete')
            ->assertSet('selected', [])
            ->assertSee('Deleted 2 imports.');

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelExists($kept);
    }

    public function test_select_all_ticks_and_unticks_every_import_on_the_page(): void
    {
        $batches = ImportBatch::factory()->count(2)->create();
        $ids = $batches->pluck('id')->map(fn (int $id): string => (string) $id)->sort()->values()->all();

        $component = Livewire::test(Index::class)->call('toggleAllOnPage');

        $this->assertEqualsCanonicalizing($ids, $component->get('selected'));

        $component->call('toggleAllOnPage')->assertSet('selected', []);
    }

    public function test_delete_selected_without_a_selection_does_nothing(): void
    {
        ImportBatch::factory()->create();

        Livewire::test(Index::class)
            ->call('confirmDelete')
            ->assertSet('showDeleteModal', false);

        $this->assertSame(1, ImportBatch::count());
    }
}
