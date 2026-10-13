<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\TrackListParser;
use App\Enums\ImportBatchStatus;
use App\Jobs\ParseImportBatchWithAiJob;
use App\Livewire\Imports\Create;
use App\Livewire\Imports\Review;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\AiTrackListParserService;
use App\Services\ImportParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AiTrackListParsingTest extends TestCase
{
    use RefreshDatabase;

    private const string BEATPORT_PASTE = "What We Got\n1\n\nWhat We Got Original Mix\nMidas Field, Marijn Jansen\ntszr\nDeep House\n128 BPM - E Minor\n2026-08-07\n$1.49";

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.providers.openai.key' => 'test-key']);
    }

    /**
     * @return array{tracks: list<array{artist: ?string, title: string, genre: ?string, source: string}>}
     */
    private function fakeTracks(): array
    {
        return ['tracks' => [
            ['artist' => 'Midas Field, Marijn Jansen', 'title' => 'What We Got (Original Mix)', 'genre' => 'Deep House', 'source' => 'What We Got Original Mix | Midas Field, Marijn Jansen | tszr | Deep House'],
            ['artist' => null, 'title' => 'Noir Muse (Original Mix)', 'genre' => null, 'source' => 'Noir Muse Original Mix'],
            ['artist' => 'Nobody', 'title' => '  ', 'genre' => null, 'source' => 'junk'],
        ]];
    }

    public function test_ai_parsing_is_enabled_only_when_the_provider_has_a_key(): void
    {
        $this->assertTrue(app(AiTrackListParserService::class)->isEnabled());

        config(['ai.providers.openai.key' => null]);

        $this->assertFalse(app(AiTrackListParserService::class)->isEnabled());
    }

    public function test_service_maps_ai_tracks_to_parsed_lines(): void
    {
        TrackListParser::fake([$this->fakeTracks()])->preventStrayPrompts();

        $lines = app(AiTrackListParserService::class)->parse(self::BEATPORT_PASTE);

        TrackListParser::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('128 BPM - E Minor'));

        $this->assertCount(2, $lines);
        $this->assertSame([
            'line_number' => 1,
            'raw_line' => 'What We Got Original Mix | Midas Field, Marijn Jansen | tszr | Deep House',
            'artist' => 'Midas Field, Marijn Jansen',
            'title' => 'What We Got (Original Mix)',
            'genre' => 'Deep House',
            'normalized_key' => 'midas field marijn jansen|what we got',
            'error_message' => null,
        ], $lines[0]);
        $this->assertSame(2, $lines[1]['line_number']);
        $this->assertNull($lines[1]['artist']);
        $this->assertSame(ImportParserService::ARTIST_NOT_DETECTED, $lines[1]['error_message']);
    }

    public function test_new_import_queues_ai_parsing_and_redirects_to_review(): void
    {
        Queue::fake();

        Livewire::test(Create::class)
            ->assertSee('AI parsing on')
            ->set('rawInput', self::BEATPORT_PASTE)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('imports.review', ImportBatch::sole()));

        $batch = ImportBatch::sole();

        $this->assertSame(ImportBatchStatus::Parsing, $batch->status);
        $this->assertSame(0, $batch->items()->count());
        Queue::assertPushed(ParseImportBatchWithAiJob::class, fn (ParseImportBatchWithAiJob $job): bool => $job->batch->is($batch));
    }

    public function test_ai_mode_limits_the_input_length_instead_of_the_line_count(): void
    {
        Queue::fake();

        Livewire::test(Create::class)
            ->set('rawInput', str_repeat('x', Create::MAX_AI_INPUT_LENGTH + 1))
            ->call('save')
            ->assertHasErrors(['rawInput' => 'max']);

        Queue::assertNothingPushed();
    }

    public function test_job_stores_items_with_default_genre_fallback_and_marks_batch_parsed(): void
    {
        TrackListParser::fake([$this->fakeTracks()])->preventStrayPrompts();
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Parsing, 'default_genre' => 'House', 'raw_input' => self::BEATPORT_PASTE]);

        ParseImportBatchWithAiJob::dispatchSync($batch);

        $this->assertSame(ImportBatchStatus::Parsed, $batch->refresh()->status);
        $this->assertSame(['Deep House', 'House'], $batch->items()->orderBy('line_number')->pluck('genre')->all());
    }

    public function test_job_does_nothing_when_the_batch_is_no_longer_parsing(): void
    {
        TrackListParser::fake()->preventStrayPrompts();
        $batch = ImportBatch::factory()->parsed()->create();

        ParseImportBatchWithAiJob::dispatchSync($batch);

        TrackListParser::assertNeverPrompted();
    }

    public function test_failed_job_marks_the_batch_failed(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Parsing]);

        (new ParseImportBatchWithAiJob($batch))->failed(new RuntimeException('Timeout'));

        $this->assertSame(ImportBatchStatus::Failed, $batch->refresh()->status);
    }

    public function test_review_polls_while_parsing_and_loads_items_when_done(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Parsing]);

        $component = Livewire::test(Review::class, ['batch' => $batch])
            ->assertSee('Parsing your track list with AI')
            ->assertSeeHtml('wire:poll.2s="checkParsing"');

        $item = ImportItem::factory()->for($batch)->create(['title' => 'What We Got (Original Mix)']);
        $batch->update(['status' => ImportBatchStatus::Parsed]);

        $component->call('checkParsing')
            ->assertDontSee('Parsing your track list with AI')
            ->assertSet("items.{$item->id}.title", 'What We Got (Original Mix)');
    }

    public function test_failed_batch_can_be_retried_with_ai_or_parsed_line_by_line(): void
    {
        Queue::fake();
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Failed, 'raw_input' => "Bicep - Glue\nAdam Beyer - Teach Me"]);

        $component = Livewire::test(Review::class, ['batch' => $batch])
            ->assertSee('AI parsing failed')
            ->call('retryAiParsing');

        $this->assertSame(ImportBatchStatus::Parsing, $batch->refresh()->status);
        Queue::assertPushed(ParseImportBatchWithAiJob::class);

        $batch->update(['status' => ImportBatchStatus::Failed]);

        $component->call('useLineParser')->assertSee('Glue');

        $this->assertSame(ImportBatchStatus::Parsed, $batch->refresh()->status);
        $this->assertSame(2, $batch->items()->count());
    }
}
