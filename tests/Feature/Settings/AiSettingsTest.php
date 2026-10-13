<?php

namespace Tests\Feature\Settings;

use App\Ai\Agents\TrackListParser;
use App\Enums\ImportBatchStatus;
use App\Jobs\ParseImportBatchWithAiJob;
use App\Livewire\Imports\Create;
use App\Livewire\Imports\Review;
use App\Livewire\Settings\Ai;
use App\Models\AiUsageLog;
use App\Models\ImportBatch;
use App\Models\Setting;
use App\Services\AiSettingsService;
use App\Services\AiTrackListParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const string SAVED_KEY = 'sk-saved-0123456789abcdefWXYZ';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.openai.key' => null,
            'services.track_list_ai.provider' => 'openai',
            'services.track_list_ai.model' => 'gpt-4.1-mini',
        ]);
    }

    public function test_page_shows_this_months_usage_and_recent_calls(): void
    {
        $batch = ImportBatch::factory()->create(['name' => 'October set']);
        AiUsageLog::factory()->for($batch)->create(['cost_usd' => '0.250000', 'input_tokens' => 1000, 'output_tokens' => 500]);
        AiUsageLog::factory()->failed()->create();
        AiUsageLog::factory()->create(['cost_usd' => '9.000000', 'created_at' => now()->subMonth()->startOfMonth()]);
        app(AiSettingsService::class)->saveModelAndBudget('gpt-4.1-mini', 1.0);

        $this->get(route('settings.ai'))
            ->assertOk()
            ->assertSeeLivewire(Ai::class)
            ->assertSee('$0.2500')
            ->assertSee('of $1.00 budget')
            ->assertSee('25% used.')
            ->assertSee('1 failed')
            ->assertSee('October set')
            ->assertSee('No key set: AI parsing is off.');
    }

    public function test_saved_key_is_encrypted_masked_and_overrides_the_env_key(): void
    {
        config(['ai.providers.openai.key' => 'sk-env-key-0000000000001111']);

        Livewire::test(Ai::class)
            ->set('apiKey', self::SAVED_KEY)
            ->call('saveApiKey')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('settings.ai');

        $stored = Setting::find(AiSettingsService::API_KEY)->value;
        $this->assertStringNotContainsString(self::SAVED_KEY, $stored);
        $this->assertSame(self::SAVED_KEY, Crypt::decryptString($stored));

        $aiSettings = app(AiSettingsService::class);
        $this->assertSame(self::SAVED_KEY, $aiSettings->apiKey());
        $this->assertSame('settings', $aiSettings->apiKeySource());
        $this->assertSame('sk-…WXYZ', $aiSettings->maskedApiKey());

        $this->get(route('settings.ai'))->assertSee('sk-…WXYZ')->assertDontSee(self::SAVED_KEY);
    }

    public function test_removing_the_saved_key_falls_back_to_the_env_key(): void
    {
        config(['ai.providers.openai.key' => 'sk-env-key-0000000000001111']);
        app(AiSettingsService::class)->saveApiKey(self::SAVED_KEY);

        Livewire::test(Ai::class)->call('removeApiKey');

        $this->assertSame('env', app(AiSettingsService::class)->apiKeySource());
        $this->assertSame('sk-env-key-0000000000001111', app(AiSettingsService::class)->apiKey());
    }

    public function test_ai_parsing_uses_the_saved_key_and_model_and_restores_the_config(): void
    {
        app(AiSettingsService::class)->saveApiKey(self::SAVED_KEY);
        app(AiSettingsService::class)->saveModelAndBudget('gpt-4.1-nano', null);
        $keyDuringPrompt = null;
        TrackListParser::fake(function () use (&$keyDuringPrompt): array {
            $keyDuringPrompt = config('ai.providers.openai.key');

            return ['tracks' => [['artist' => 'Bicep', 'title' => 'Glue', 'genre' => null, 'source' => 'Bicep - Glue']]];
        });

        app(AiTrackListParserService::class)->parse('Bicep - Glue');

        $this->assertSame(self::SAVED_KEY, $keyDuringPrompt);
        $this->assertNull(config('ai.providers.openai.key'));
        $this->assertSame('gpt-4.1-nano', AiUsageLog::sole()->model);
    }

    public function test_key_test_reports_valid_and_invalid_keys(): void
    {
        app(AiSettingsService::class)->saveApiKey(self::SAVED_KEY);
        Http::fakeSequence('api.openai.com/*')->push(['data' => []])->push(['error' => []], 401);

        Livewire::test(Ai::class)
            ->call('testApiKey')
            ->assertSet('keyTestResult', 'valid')
            ->call('testApiKey')
            ->assertSet('keyTestResult', 'invalid');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer '.self::SAVED_KEY));
    }

    public function test_model_and_budget_are_validated_and_saved(): void
    {
        Livewire::test(Ai::class)
            ->set('model', '')
            ->set('monthlyBudget', '-1')
            ->call('saveSettings')
            ->assertHasErrors(['model', 'monthlyBudget'])
            ->set('model', 'gpt-4.1-mini')
            ->set('monthlyBudget', '4')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame(4.0, app(AiSettingsService::class)->monthlyBudget());

        Livewire::test(Ai::class)->set('monthlyBudget', '')->call('saveSettings');

        $this->assertNull(app(AiSettingsService::class)->monthlyBudget());
    }

    public function test_reached_budget_switches_new_imports_to_the_line_parser(): void
    {
        Queue::fake();
        app(AiSettingsService::class)->saveApiKey(self::SAVED_KEY);
        app(AiSettingsService::class)->saveModelAndBudget('gpt-4.1-mini', 1.0);
        AiUsageLog::factory()->create(['cost_usd' => '1.000000']);

        Livewire::test(Create::class)
            ->assertSee('The monthly AI budget is reached')
            ->set('rawInput', 'Bicep - Glue')
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertNotPushed(ParseImportBatchWithAiJob::class);
        $this->assertSame(ImportBatchStatus::Parsed, ImportBatch::sole()->status);
    }

    public function test_budget_warning_shows_at_80_percent(): void
    {
        app(AiSettingsService::class)->saveApiKey(self::SAVED_KEY);
        app(AiSettingsService::class)->saveModelAndBudget('gpt-4.1-mini', 1.0);
        AiUsageLog::factory()->create(['cost_usd' => '0.850000']);

        $this->assertTrue(app(AiTrackListParserService::class)->isEnabled());

        Livewire::test(Create::class)->assertSee('Over 80% of the monthly AI budget is used.');
    }

    public function test_retry_with_ai_is_blocked_when_the_budget_is_reached(): void
    {
        Queue::fake();
        app(AiSettingsService::class)->saveApiKey(self::SAVED_KEY);
        app(AiSettingsService::class)->saveModelAndBudget('gpt-4.1-mini', 0.5);
        AiUsageLog::factory()->create(['cost_usd' => '0.500000']);
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Failed]);

        Livewire::test(Review::class, ['batch' => $batch])
            ->call('retryAiParsing')
            ->assertHasErrors('retry');

        Queue::assertNotPushed(ParseImportBatchWithAiJob::class);
        $this->assertSame(ImportBatchStatus::Failed, $batch->refresh()->status);
    }
}
