<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\TrackListParser;
use App\Enums\AiUsageStatus;
use App\Enums\ImportBatchStatus;
use App\Jobs\ParseImportBatchWithAiJob;
use App\Models\AiUsageLog;
use App\Models\ImportBatch;
use App\Services\AiTrackListParserService;
use App\Services\AiUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use RuntimeException;
use Tests\TestCase;

class AiUsageLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.openai.key' => 'test-key',
            'services.track_list_ai.provider' => 'openai',
            'services.track_list_ai.model' => 'gpt-4.1-mini',
            'services.ai_pricing' => ['openai' => ['gpt-4.1-mini' => ['input' => 0.40, 'cached_input' => 0.10, 'output' => 1.60]]],
        ]);
    }

    private function fakeResponse(TextUsage $usage): StructuredTextResponse
    {
        $tracks = ['tracks' => [['artist' => 'Bicep', 'title' => 'Glue', 'genre' => null, 'source' => 'Bicep - Glue']]];

        return new StructuredTextResponse($tracks, json_encode($tracks), $usage, new Meta('openai', 'gpt-4.1-mini'));
    }

    public function test_successful_ai_parsing_logs_tokens_cost_and_batch(): void
    {
        TrackListParser::fake([$this->fakeResponse(new TextUsage(inputTokens: 10_000, outputTokens: 2_000, cacheReadInputTokens: 4_000))])->preventStrayPrompts();
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Parsing, 'raw_input' => 'Bicep - Glue']);

        ParseImportBatchWithAiJob::dispatchSync($batch);

        $log = AiUsageLog::sole();

        $this->assertTrue($log->importBatch->is($batch));
        $this->assertSame(TrackListParser::class, $log->agent);
        $this->assertSame('openai', $log->provider);
        $this->assertSame('gpt-4.1-mini', $log->model);
        $this->assertSame(10_000, $log->input_tokens);
        $this->assertSame(4_000, $log->cached_input_tokens);
        $this->assertSame(2_000, $log->output_tokens);
        // 6000 × 0.40 + 4000 × 0.10 + 2000 × 1.60 = 6000 per 1M tokens
        $this->assertSame('0.006000', $log->cost_usd);
        $this->assertSame(AiUsageStatus::Succeeded, $log->status);
        $this->assertNotNull($log->duration_ms);
    }

    public function test_failed_ai_call_is_logged_without_cost(): void
    {
        TrackListParser::fake(fn () => throw new RuntimeException('Request timed out.'));
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Parsing]);

        try {
            ParseImportBatchWithAiJob::dispatchSync($batch);
            $this->fail('The job should have thrown.');
        } catch (RuntimeException) {
        }

        $log = AiUsageLog::sole();

        $this->assertSame(AiUsageStatus::Failed, $log->status);
        $this->assertSame('Request timed out.', $log->error_message);
        $this->assertSame(0, $log->input_tokens);
        $this->assertSame('0.000000', $log->cost_usd);
        $this->assertTrue($log->importBatch->is($batch));
    }

    public function test_calls_outside_a_batch_are_logged_without_batch(): void
    {
        TrackListParser::fake([$this->fakeResponse(new TextUsage(100, 50))])->preventStrayPrompts();

        app(AiTrackListParserService::class)->parse('Bicep - Glue');

        $this->assertNull(AiUsageLog::sole()->import_batch_id);
    }

    public function test_model_without_price_is_logged_without_cost(): void
    {
        config(['services.track_list_ai.model' => 'some-new-model']);
        TrackListParser::fake([$this->fakeResponse(new TextUsage(100, 50))])->preventStrayPrompts();

        app(AiTrackListParserService::class)->parse('Bicep - Glue');

        $log = AiUsageLog::sole();

        $this->assertSame('some-new-model', $log->model);
        $this->assertSame(100, $log->input_tokens);
        $this->assertNull($log->cost_usd);
    }

    public function test_cost_estimate_uses_the_input_price_when_there_is_no_cached_price(): void
    {
        config(['services.ai_pricing.openai' => ['test-model' => ['input' => 1.00, 'output' => 2.00]]]);

        $this->assertSame(0.004, app(AiUsageService::class)->estimateCost('openai', 'test-model', 2_000, 1_000, 1_000));
    }

    public function test_cost_estimate_reads_the_shipped_price_of_a_dotted_model_name(): void
    {
        config(['services.ai_pricing' => (require config_path('services.php'))['ai_pricing']]);

        // 1M input tokens × $0.40
        $this->assertSame(0.4, app(AiUsageService::class)->estimateCost('openai', 'gpt-4.1-mini', 1_000_000, 0, 0));
    }
}
