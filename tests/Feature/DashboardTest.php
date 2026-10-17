<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Livewire\Dashboard;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\SupersimpleTxtExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_renders_inside_the_app_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeLivewire(Dashboard::class)
            ->assertSee('<title>Dashboard · '.config('app.name').'</title>', false)
            ->assertSee('data-accent="'.config('app.accent').'"', false);
    }

    public function test_navigation_links_to_every_screen(): void
    {
        $this->get('/')
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('imports.index').'"', false)
            ->assertSee('href="'.route('exports.index').'"', false)
            ->assertDontSee('title="Coming soon"', false);
    }

    public function test_nav_link_without_a_route_is_shown_as_coming_soon(): void
    {
        $this->blade('<x-nav-link route="not.built.yet">Later</x-nav-link>')
            ->assertSee('title="Coming soon">Later</span>', false);
    }

    public function test_accent_comes_from_config(): void
    {
        config(['app.accent' => 'indigo']);

        $this->get('/')->assertSee('data-accent="indigo"', false);
    }

    public function test_how_it_works_modal_is_bound_to_a_property(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSet('showHowItWorks', false)
            ->assertSeeHtml('wire:model="showHowItWorks"')
            ->set('showHowItWorks', true)
            ->assertSet('showHowItWorks', true);
    }

    public function test_empty_dashboard_shows_the_empty_state(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('No imports yet')
            ->assertDontSee('Needs attention');
    }

    public function test_imports_to_review_need_attention(): void
    {
        $batch = ImportBatch::factory()->parsed()->create(['name' => 'Fresh paste']);
        ImportItem::factory()->for($batch)->count(3)->create();
        ImportBatch::factory()->reviewed()->create(['name' => 'Already reviewed']);

        Livewire::test(Dashboard::class)
            ->assertSee('Fresh paste')
            ->assertSee('3 tracks to review')
            ->assertSeeHtml('href="'.route('imports.review', $batch).'"')
            ->assertDontSee('Already reviewed · ');
    }

    public function test_searched_imports_with_tracks_without_a_link_need_attention(): void
    {
        $batch = ImportBatch::factory()->create(['name' => 'Half done', 'status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create();
        ImportItem::factory()->for($batch)->count(2)->create();
        $complete = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($complete)->withSelectedYoutubeLink()->create();

        Livewire::test(Dashboard::class)
            ->assertSee('2 of 3 tracks without a link')
            ->assertSeeHtml('href="'.route('imports.show', $batch).'" wire:navigate class="font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Add links')
            ->assertDontSeeHtml('href="'.route('imports.show', $complete).'" wire:navigate class="font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Add links');
    }

    public function test_an_export_becomes_outdated_when_its_links_change(): void
    {
        Storage::fake('local');
        $batch = ImportBatch::factory()->create(['name' => 'Exported batch', 'status' => ImportBatchStatus::Searched]);
        $item = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create();
        app(SupersimpleTxtExportService::class)->export($batch);

        Livewire::test(Dashboard::class)
            ->assertSee('All caught up')
            ->assertDontSee('links changed since the last TXT');

        $item->update(['include_in_export' => false]);

        Livewire::test(Dashboard::class)
            ->assertSee('Exported batch')
            ->assertSee('links changed since the last TXT')
            ->assertSee('Export again');
    }

    public function test_edits_that_do_not_change_the_links_keep_the_export_up_to_date(): void
    {
        Storage::fake('local');
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        $item = ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create();
        app(SupersimpleTxtExportService::class)->export($batch);

        $item->update(['title' => 'Renamed (Extended Mix)', 'bpm' => 125]);

        Livewire::test(Dashboard::class)->assertDontSee('links changed since the last TXT');
    }

    public function test_recent_imports_and_exports_show_the_ten_newest_with_view_all_links(): void
    {
        foreach (range(1, 11) as $number) {
            $batch = ImportBatch::factory()->create(['name' => "Import {$number}", 'created_at' => now()->subMinutes(20 - $number)]);
        }
        $export = ExportFile::factory()->for($batch)->create(['filename' => 'newest_youtube_links.txt']);

        Livewire::test(Dashboard::class)
            ->assertSeeInOrder(['Recent imports', 'Import 11', 'Import 2', 'Recent exports', 'newest_youtube_links.txt'])
            ->assertDontSee('Import 1<')
            ->assertSeeHtml('href="'.route('imports.index').'"')
            ->assertSeeHtml('href="'.route('exports.index').'"')
            ->assertSeeHtml('href="'.route('exports.download', $export).'"');
    }
}
