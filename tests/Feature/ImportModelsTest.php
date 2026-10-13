<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\YoutubeCandidate;
use App\Models\YoutubeSearchCache;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_has_items_and_export_files(): void
    {
        $batch = ImportBatch::factory()
            ->has(ImportItem::factory()->count(2), 'items')
            ->has(ExportFile::factory(), 'exportFiles')
            ->create();

        $this->assertCount(2, $batch->items);
        $this->assertCount(1, $batch->exportFiles);
        $this->assertTrue($batch->items->first()->importBatch->is($batch));
    }

    public function test_item_has_youtube_candidates(): void
    {
        $item = ImportItem::factory()
            ->has(YoutubeCandidate::factory()->count(3))
            ->create();

        $this->assertCount(3, $item->youtubeCandidates);
        $this->assertTrue($item->youtubeCandidates->first()->importItem->is($item));
    }

    public function test_statuses_are_cast_to_enums_with_defaults(): void
    {
        $batch = ImportBatch::create(['name' => 'YouTube Export', 'raw_input' => 'A - B']);
        $item = $batch->items()->create([
            'line_number' => 1,
            'raw_line' => 'A - B',
            'artist' => 'A',
            'title' => 'B',
            'normalized_key' => 'a b',
        ]);

        $this->assertSame(ImportBatchStatus::Draft, $batch->fresh()->status);
        $this->assertSame(ImportItemStatus::Parsed, $item->fresh()->status);
        $this->assertFalse($item->fresh()->include_in_export);
    }

    public function test_batch_with_items_cannot_be_deleted(): void
    {
        $item = ImportItem::factory()->create();

        $this->expectException(QueryException::class);

        $item->importBatch->delete();
    }

    public function test_search_cache_uses_singular_table_and_casts_results(): void
    {
        $cache = YoutubeSearchCache::factory()->create(['results' => [['video_id' => 'abc123']]]);

        $this->assertSame('youtube_search_cache', $cache->getTable());
        $this->assertSame([['video_id' => 'abc123']], $cache->fresh()->results);
    }
}
