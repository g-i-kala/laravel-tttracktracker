<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Livewire\Imports\Show;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SupersimpleTxtExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_export_writes_only_included_links_one_per_line_and_starts_the_download(): void
    {
        $batch = ImportBatch::factory()->create(['name' => 'Techno October', 'status' => ImportBatchStatus::Searched]);
        $second = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 2]);
        $first = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 1]);
        $excluded = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 3, 'include_in_export' => false]);

        Livewire::test(Show::class, ['batch' => $batch])
            ->call('export')
            ->assertSet('showMissingLinksModal', false)
            ->assertSet('showVersionModal', false)
            ->assertDispatched('download-export', url: route('exports.download', ExportFile::sole()))
            ->assertSee('Exported 2 links')
            ->assertSee('techno-october_youtube_links.txt');

        $export = ExportFile::sole();

        $this->assertSame('techno-october_youtube_links.txt', $export->filename);
        $this->assertSame(1, $export->version);
        $this->assertSame(2, $export->items_count);
        $this->assertSame("{$first->selected_youtube_url}\n{$second->selected_youtube_url}", Storage::disk('local')->get($export->path));

        $this->assertSame(ImportBatchStatus::Exported, $batch->refresh()->status);
        $this->assertSame(ImportItemStatus::Exported, $first->refresh()->status);
        $this->assertSame(ImportItemStatus::Searched, $excluded->refresh()->status);
    }

    public function test_tracks_without_a_link_open_the_missing_links_modal_and_can_be_skipped(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $withLink = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 1]);
        ImportItem::factory()->for($batch)->create(['line_number' => 2, 'artist' => 'Bicep', 'title' => 'Glue']);

        $component = Livewire::test(Show::class, ['batch' => $batch])
            ->call('export')
            ->assertSet('showMissingLinksModal', true)
            ->assertSee('1 track has no YouTube link and will be skipped.')
            ->assertSee('#2 Bicep - Glue')
            ->assertNotDispatched('download-export');

        $this->assertSame(0, ExportFile::count());

        $component->call('exportAnyway')
            ->assertSet('showMissingLinksModal', false)
            ->assertDispatched('download-export');

        $this->assertSame($withLink->selected_youtube_url, Storage::disk('local')->get(ExportFile::sole()->path));
    }

    public function test_existing_export_asks_and_creates_a_new_version(): void
    {
        $batch = ImportBatch::factory()->create(['name' => 'Techno October', 'status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create();

        $component = Livewire::test(Show::class, ['batch' => $batch])->call('export');
        $component->call('export')
            ->assertSet('showVersionModal', true)
            ->assertSee('Export already exists');

        $this->assertSame(1, ExportFile::count());

        $component->call('confirmExport', 'new')->assertSet('showVersionModal', false);

        $latest = ExportFile::orderByDesc('version')->first();

        $this->assertSame(2, ExportFile::count());
        $this->assertSame(2, $latest->version);
        $this->assertSame('techno-october_youtube_links_v2.txt', $latest->filename);
        Storage::disk('local')->assertExists([
            "exports/{$batch->id}/techno-october_youtube_links.txt",
            "exports/{$batch->id}/techno-october_youtube_links_v2.txt",
        ]);
    }

    public function test_overwrite_latest_rewrites_the_file_and_keeps_the_version(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $first = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 1]);
        $second = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 2, 'include_in_export' => false]);

        $component = Livewire::test(Show::class, ['batch' => $batch])->call('export');

        $component->call('toggleExport', $second->id)
            ->call('export')
            ->call('confirmExport', 'overwrite');

        $export = ExportFile::sole();

        $this->assertSame(1, $export->version);
        $this->assertSame(2, $export->items_count);
        $this->assertSame("{$first->selected_youtube_url}\n{$second->selected_youtube_url}", Storage::disk('local')->get($export->path));
    }

    public function test_generate_is_disabled_and_does_nothing_without_exportable_links(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($batch)->create();

        Livewire::test(Show::class, ['batch' => $batch])
            ->assertSeeHtml('title="Paste at least one link first"')
            ->call('export')
            ->call('confirmExport', 'new')
            ->assertSet('showMissingLinksModal', false)
            ->assertNotDispatched('download-export');

        $this->assertSame(0, ExportFile::count());
    }

    public function test_download_returns_the_file_as_an_attachment(): void
    {
        $export = ExportFile::factory()->create(['filename' => 'techno-october_youtube_links.txt', 'path' => 'exports/1/techno-october_youtube_links.txt']);
        Storage::disk('local')->put($export->path, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $response = $this->get(route('exports.download', $export));

        $response->assertOk()
            ->assertDownload('techno-october_youtube_links.txt')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $response->streamedContent());
    }

    public function test_download_of_a_missing_file_is_not_found(): void
    {
        $export = ExportFile::factory()->create();

        $this->get(route('exports.download', $export))->assertNotFound();
    }
}
