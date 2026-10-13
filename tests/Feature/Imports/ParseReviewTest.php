<?php

namespace Tests\Feature\Imports;

use App\Livewire\Imports\Review;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\ImportParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParseReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_items_in_line_order(): void
    {
        $batch = ImportBatch::factory()->parsed()->create(['name' => 'Techno October']);
        ImportItem::factory()->for($batch)->create(['line_number' => 2, 'artist' => 'Bicep', 'title' => 'Glue']);
        ImportItem::factory()->for($batch)->create(['line_number' => 1, 'artist' => 'Adam Beyer', 'title' => 'Teach Me']);

        $this->get(route('imports.review', $batch))
            ->assertOk()
            ->assertSeeLivewire(Review::class)
            ->assertSee('Techno October')
            ->assertSeeInOrder(['Teach Me', 'Glue']);
    }

    public function test_editing_a_field_saves_it_and_recomputes_the_normalized_key(): void
    {
        $item = ImportItem::factory()->create(['artist' => 'Adam Beyer', 'title' => 'Teach Me']);

        Livewire::test(Review::class, ['batch' => $item->importBatch])
            ->set("items.{$item->id}.title", '  Your Mind  (Original Mix) ')
            ->assertHasNoErrors()
            ->assertSet('savedItemId', $item->id);

        $item->refresh();

        $this->assertSame('Your Mind (Original Mix)', $item->title);
        $this->assertSame('adam beyer|your mind', $item->normalized_key);
    }

    public function test_filling_in_a_missing_artist_clears_the_notice_and_clearing_it_sets_it_again(): void
    {
        $item = ImportItem::factory()->create([
            'artist' => null,
            'title' => 'Teach Me',
            'error_message' => ImportParserService::ARTIST_NOT_DETECTED,
        ]);

        $component = Livewire::test(Review::class, ['batch' => $item->importBatch])
            ->set("items.{$item->id}.artist", 'Adam Beyer');

        $this->assertNull($item->refresh()->error_message);
        $this->assertSame('adam beyer|teach me', $item->normalized_key);

        $component->set("items.{$item->id}.artist", '');

        $this->assertNull($item->refresh()->artist);
        $this->assertSame(ImportParserService::ARTIST_NOT_DETECTED, $item->error_message);
    }

    public function test_empty_genre_is_stored_as_null(): void
    {
        $item = ImportItem::factory()->create(['genre' => 'Techno']);

        Livewire::test(Review::class, ['batch' => $item->importBatch])
            ->set("items.{$item->id}.genre", ' ');

        $this->assertNull($item->refresh()->genre);
    }

    public function test_title_is_required(): void
    {
        $item = ImportItem::factory()->create(['title' => 'Teach Me']);

        Livewire::test(Review::class, ['batch' => $item->importBatch])
            ->set("items.{$item->id}.title", '')
            ->assertHasErrors(["items.{$item->id}.title" => 'required']);

        $this->assertSame('Teach Me', $item->refresh()->title);
    }

    public function test_an_item_can_be_removed(): void
    {
        $batch = ImportBatch::factory()->create();
        [$kept, $removed] = ImportItem::factory()->for($batch)->count(2)->create();

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('removeItem', $removed->id)
            ->assertSet("items.{$removed->id}", null);

        $this->assertModelMissing($removed);
        $this->assertModelExists($kept);
    }

    public function test_items_of_another_batch_cannot_be_edited_or_removed(): void
    {
        $batch = ImportBatch::factory()->create();
        $foreignItem = ImportItem::factory()->create(['title' => 'Foreign']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('removeItem', $foreignItem->id)
            ->assertNotFound();

        $this->assertModelExists($foreignItem);
    }
}
