<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportBatchStatus;
use App\Livewire\Imports\Index;
use App\Livewire\Imports\Review;
use App\Livewire\Imports\Show;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrackListExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_txt_lists_every_track_in_line_order(): void
    {
        $batch = ImportBatch::factory()->create(['name' => '80s Party']);
        ImportItem::factory()->for($batch)->create(['line_number' => 3, 'artist' => null, 'title' => 'Untitled Jam']);
        ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 1, 'artist' => 'a-ha', 'title' => 'Take On Me', 'include_in_export' => false]);
        ImportItem::factory()->for($batch)->create(['line_number' => 2, 'artist' => 'Lady Pank', 'title' => 'Mniej niż zero']);

        $response = $this->get(route('imports.track-list', ['batch' => $batch, 'format' => 'txt']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertDownload('80s-party_tracks.txt');
        $this->assertSame("a-ha - Take On Me\nLady Pank - Mniej niż zero\nUntitled Jam", $response->streamedContent());
    }

    public function test_csv_has_a_header_all_fields_and_the_youtube_link(): void
    {
        $batch = ImportBatch::factory()->create(['name' => '80s Party']);
        ImportItem::factory()->for($batch)->create([
            'line_number' => 1,
            'artist' => 'Kombi',
            'title' => 'Black and White, "Live"',
            'genre' => '80s',
            'bpm' => 120,
            'musical_key' => 'A minor',
            'camelot_key' => '8A',
            'label' => 'Polskie Nagrania',
            'selected_youtube_url' => 'https://www.youtube.com/watch?v=kombi000001',
        ]);
        ImportItem::factory()->for($batch)->create([
            'line_number' => 2, 'artist' => null, 'title' => 'Glue', 'genre' => null, 'bpm' => null,
            'musical_key' => null, 'camelot_key' => null, 'label' => null, 'selected_youtube_url' => null,
        ]);

        $response = $this->get(route('imports.track-list', ['batch' => $batch, 'format' => 'csv']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('80s-party_tracks.csv');
        $this->assertSame(
            "\u{FEFF}Artist,Title,Genre,BPM,Key,Camelot,Label,\"YouTube URL\"\n"
            ."Kombi,\"Black and White, \"\"Live\"\"\",80s,120,\"A minor\",8A,\"Polskie Nagrania\",https://www.youtube.com/watch?v=kombi000001\n"
            .",Glue,,,,,,\n",
            $response->streamedContent(),
        );
    }

    public function test_unknown_format_and_missing_import_are_not_found(): void
    {
        $batch = ImportBatch::factory()->create();

        $this->get("/imports/{$batch->id}/track-list/xlsx")->assertNotFound();
        $this->get('/imports/999/track-list/txt')->assertNotFound();
    }

    public function test_review_results_and_imports_list_offer_the_track_list(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($batch)->withSelectedYoutubeLink()->create(['line_number' => 1]);
        $csvUrl = route('imports.track-list', ['batch' => $batch, 'format' => 'csv']);

        Livewire::test(Review::class, ['batch' => $batch])->assertSee('Export track list')->assertSee($csvUrl, escape: false);
        Livewire::test(Show::class, ['batch' => $batch])->assertSee('Export track list')->assertSee($csvUrl, escape: false);
        Livewire::test(Index::class)->assertSee('Track list')->assertSee($csvUrl, escape: false);
    }
}
