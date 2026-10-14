<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportBatchStatus;
use App\Livewire\Imports\Review;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParseReviewDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_opens_right_after_parsing_when_there_are_exact_duplicates(): void
    {
        $batch = $this->batchWithDuplicates();

        $this->withSession(['check_duplicates' => true]);

        Livewire::test(Review::class, ['batch' => $batch])
            ->assertSet('showDuplicateModal', true)
            ->assertSee('Duplicates found')
            ->assertSee('#1 Adam Beyer - Teach Me')
            ->assertSee('also #3');
    }

    public function test_later_visits_show_only_the_banner_and_row_badges(): void
    {
        $batch = $this->batchWithDuplicates();

        Livewire::test(Review::class, ['batch' => $batch])
            ->assertSet('showDuplicateModal', false)
            ->assertSee('1 exact duplicate in this import.')
            ->assertSee('Duplicate of #1');
    }

    public function test_modal_does_not_open_without_exact_duplicates(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        ImportItem::factory()->for($batch)->count(2)->sequence(['title' => 'Glue'], ['title' => 'Kerala'])->create();

        $this->withSession(['check_duplicates' => true]);

        Livewire::test(Review::class, ['batch' => $batch])
            ->assertSet('showDuplicateModal', false)
            ->assertDontSee('exact duplicate');
    }

    public function test_modal_opens_when_ai_parsing_finishes_with_exact_duplicates(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Parsing]);

        $component = Livewire::test(Review::class, ['batch' => $batch])
            ->assertSet('showDuplicateModal', false);

        ImportItem::factory()->for($batch)->count(2)->sequence(['line_number' => 1], ['line_number' => 2])->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $batch->update(['status' => ImportBatchStatus::Parsed]);

        $component->call('checkParsing')
            ->assertSet('showDuplicateModal', true);
    }

    public function test_remove_duplicates_keeps_the_first_of_each_group(): void
    {
        $batch = $this->batchWithDuplicates();
        [$first, $other, $duplicate] = $batch->items()->orderBy('line_number')->get();

        Livewire::test(Review::class, ['batch' => $batch])
            ->set('showDuplicateModal', true)
            ->call('removeDuplicates')
            ->assertSet('showDuplicateModal', false)
            ->assertSet("items.{$duplicate->id}", null)
            ->assertSee('Removed 1 duplicate.')
            ->assertDontSee('exact duplicate in this import');

        $this->assertModelExists($first);
        $this->assertModelExists($other);
        $this->assertModelMissing($duplicate);
    }

    public function test_keep_all_keeps_every_track_and_hides_the_banner(): void
    {
        $batch = $this->batchWithDuplicates();

        Livewire::test(Review::class, ['batch' => $batch])
            ->set('showDuplicateModal', true)
            ->call('keepAllDuplicates')
            ->assertSet('showDuplicateModal', false)
            ->assertDontSee('exact duplicate in this import')
            ->assertSee('Duplicate of #1');

        $this->assertSame(3, $batch->items()->count());
    }

    public function test_similar_tracks_are_marked_without_blocking(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        ImportItem::factory()->for($batch)->create(['line_number' => 1, 'artist' => 'Bicep', 'title' => 'Glue']);
        ImportItem::factory()->for($batch)->create(['line_number' => 2, 'artist' => 'Bicep', 'title' => 'Glue (Extended Mix)']);

        $this->withSession(['check_duplicates' => true]);

        Livewire::test(Review::class, ['batch' => $batch])
            ->assertSet('showDuplicateModal', false)
            ->assertSee('2 tracks look similar')
            ->assertSee('Similar to #2')
            ->assertSee('Similar to #1');
    }

    public function test_editing_a_track_into_a_duplicate_marks_it(): void
    {
        $batch = ImportBatch::factory()->parsed()->create();
        ImportItem::factory()->for($batch)->create(['line_number' => 1, 'artist' => 'Bicep', 'title' => 'Glue']);
        $other = ImportItem::factory()->for($batch)->create(['line_number' => 2, 'artist' => 'Bicep', 'title' => 'Kerala']);

        Livewire::test(Review::class, ['batch' => $batch])
            ->assertDontSee('Duplicate of #1')
            ->set("items.{$other->id}.title", 'Glue')
            ->assertSee('Duplicate of #1');
    }

    /**
     * Three items: #1 and #3 are exact duplicates (different case), #2 is unrelated.
     */
    private function batchWithDuplicates(): ImportBatch
    {
        $batch = ImportBatch::factory()->parsed()->create();
        ImportItem::factory()->for($batch)->create(['line_number' => 1, 'artist' => 'Adam Beyer', 'title' => 'Teach Me']);
        ImportItem::factory()->for($batch)->create(['line_number' => 2, 'artist' => 'Bicep', 'title' => 'Glue']);
        ImportItem::factory()->for($batch)->create(['line_number' => 3, 'artist' => 'ADAM BEYER', 'title' => 'Teach Me']);

        return $batch;
    }
}
