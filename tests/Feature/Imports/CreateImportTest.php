<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Livewire\Imports\Create;
use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\TestCase;

class CreateImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_an_auto_generated_name(): void
    {
        Date::setTestNow('2026-10-13 18:30:00');

        $this->get(route('imports.create'))
            ->assertOk()
            ->assertSeeLivewire(Create::class);

        Livewire::test(Create::class)->assertSet('name', 'YouTube Export 2026-10-13 18:30');
    }

    public function test_it_creates_a_parsed_batch_with_items_and_redirects(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Techno October')
            ->set('year', '2026')
            ->set('month', '10')
            ->set('defaultGenre', 'Techno')
            ->set('rawInput', "Adam Beyer - Teach Me (Original Mix)\n\nTeach Me")
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $batch = ImportBatch::sole();

        $this->assertSame('Techno October', $batch->name);
        $this->assertSame(2026, $batch->year);
        $this->assertSame(10, $batch->month);
        $this->assertSame('Techno', $batch->default_genre);
        $this->assertSame(ImportBatchStatus::Parsed, $batch->status);
        $this->assertSame("Adam Beyer - Teach Me (Original Mix)\n\nTeach Me", $batch->raw_input);

        $items = $batch->items()->orderBy('line_number')->get();

        $this->assertCount(2, $items);
        $this->assertSame([1, 3], $items->pluck('line_number')->all());
        $this->assertSame(['Adam Beyer', null], $items->pluck('artist')->all());
        $this->assertSame(['Techno', 'Techno'], $items->pluck('genre')->all());
        $this->assertSame([null, 'Artist not detected'], $items->pluck('error_message')->all());
        $this->assertTrue($items->every(fn ($item) => $item->status === ImportItemStatus::Parsed));

        $this->assertSame('Imported 2 tracks into "Techno October".', session('status'));
    }

    public function test_optional_fields_can_be_left_empty(): void
    {
        Livewire::test(Create::class)
            ->set('rawInput', 'Bicep - Glue')
            ->call('save')
            ->assertHasNoErrors();

        $batch = ImportBatch::sole();

        $this->assertNull($batch->year);
        $this->assertNull($batch->month);
        $this->assertNull($batch->default_genre);
        $this->assertNull($batch->items()->sole()->genre);
    }

    public function test_it_requires_at_least_one_track(): void
    {
        Livewire::test(Create::class)
            ->set('rawInput', "  \n\n ")
            ->call('save')
            ->assertHasErrors(['rawInput']);

        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_it_rejects_more_than_the_maximum_number_of_tracks(): void
    {
        $tracks = collect(range(1, Create::MAX_TRACKS + 1))->map(fn (int $n): string => "Artist {$n} - Title {$n}")->implode("\n");

        Livewire::test(Create::class)
            ->set('rawInput', $tracks)
            ->call('save')
            ->assertHasErrors(['rawInput']);

        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_it_validates_name_year_and_month(): void
    {
        Livewire::test(Create::class)
            ->set('name', '')
            ->set('year', '1999')
            ->set('month', '13')
            ->set('rawInput', 'Bicep - Glue')
            ->call('save')
            ->assertHasErrors(['name', 'year', 'month']);
    }

    public function test_it_shows_a_live_count_of_detected_tracks(): void
    {
        Livewire::test(Create::class)
            ->set('rawInput', "Bicep - Glue\nTeach Me")
            ->assertSet('detected', ['tracks' => 2, 'withoutArtist' => 1])
            ->assertSee('2 tracks detected')
            ->assertSee('1 without artist');
    }
}
