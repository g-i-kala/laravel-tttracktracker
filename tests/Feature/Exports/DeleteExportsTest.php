<?php

namespace Tests\Feature\Exports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Livewire\Exports\Index;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\SupersimpleTxtExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteExportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_deleting_one_version_keeps_the_others_and_the_exported_status(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create();
        $exports = app(SupersimpleTxtExportService::class);
        $first = $exports->export($batch);
        $second = $exports->export($batch);

        $deleted = $exports->deleteExports([$first->id]);

        $this->assertSame(1, $deleted);
        $this->assertModelMissing($first);
        Storage::disk('local')->assertMissing($first->path);
        Storage::disk('local')->assertExists($second->path);
        $this->assertSame(ImportBatchStatus::Exported, $batch->refresh()->status);
    }

    public function test_deleting_the_last_export_puts_the_batch_and_its_items_back_to_searched(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $item = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create();
        $exports = app(SupersimpleTxtExportService::class);
        $export = $exports->export($batch);

        $exports->deleteExports([$export->id]);

        $this->assertSame(ImportBatchStatus::Searched, $batch->refresh()->status);
        $this->assertSame(ImportItemStatus::Searched, $item->refresh()->status);
        $this->assertModelExists($item);
    }

    public function test_delete_button_opens_a_modal_listing_the_file_and_deletes_on_confirm(): void
    {
        $export = ExportFile::factory()->create(['filename' => 'old_youtube_links.txt']);

        Livewire::test(Index::class)
            ->call('confirmDelete', $export->id)
            ->assertSet('showDeleteModal', true)
            ->assertSee('Delete 1 export file? The imports and their links stay.')
            ->assertSee('old_youtube_links.txt')
            ->call('delete')
            ->assertSee('Deleted 1 export file.')
            ->assertSee('No exports yet');

        $this->assertModelMissing($export);
        $this->assertModelExists($export->importBatch);
    }

    public function test_several_selected_exports_can_be_deleted_at_once(): void
    {
        [$first, $second, $kept] = ExportFile::factory()->count(3)->create();

        Livewire::test(Index::class)
            ->set('selected', [(string) $first->id, (string) $second->id])
            ->assertSee('2 selected')
            ->call('confirmDelete')
            ->assertSee('Delete 2 export files?')
            ->call('delete')
            ->assertSet('selected', [])
            ->assertSee('Deleted 2 export files.');

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelExists($kept);
    }

    public function test_select_all_ticks_and_unticks_every_export_on_the_page(): void
    {
        ExportFile::factory()->count(2)->create();

        $component = Livewire::test(Index::class)->call('toggleAllOnPage');

        $this->assertCount(2, $component->get('selected'));

        $component->call('toggleAllOnPage')->assertSet('selected', []);
    }
}
