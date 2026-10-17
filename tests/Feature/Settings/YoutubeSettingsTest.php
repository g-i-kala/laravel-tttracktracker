<?php

namespace Tests\Feature\Settings;

use App\Enums\ImportBatchStatus;
use App\Livewire\Settings\Youtube;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\YoutubeApiCall;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class YoutubeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const string QUOTA_LINE = 'YouTube quota: 1,000 / 10,000 units used today (~90 searches left) · resets ';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.youtube.api_key' => 'test-youtube-key', 'services.youtube.daily_quota' => 10000]);
        $this->travelTo(CarbonImmutable::parse('2026-10-17 01:00', 'America/Los_Angeles'));
        YoutubeApiCall::factory()->count(10)->create();
    }

    public function test_settings_page_shows_todays_quota_and_reset_time(): void
    {
        $resetsAt = CarbonImmutable::parse('2026-10-18 00:00', 'America/Los_Angeles')->setTimezone(config('app.timezone'))->format('H:i');

        $this->get(route('settings.youtube'))
            ->assertOk()
            ->assertSeeLivewire(Youtube::class)
            ->assertSee('~90')
            ->assertSee(self::QUOTA_LINE.$resetsAt)
            ->assertSee('Using the key from')
            ->assertSee('YouTube Settings');
    }

    public function test_settings_page_says_when_no_key_is_set(): void
    {
        config(['services.youtube.api_key' => '']);

        $this->get(route('settings.youtube'))
            ->assertOk()
            ->assertSee('No key set: Search YouTube creates search links you open yourself.');
    }

    public function test_quota_line_shows_on_dashboard_review_and_youtube_results(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($batch)->create();

        $this->get(route('dashboard'))->assertOk()->assertSee(self::QUOTA_LINE);
        $this->get(route('imports.review', $batch))->assertOk()->assertSee(self::QUOTA_LINE);
        $this->get(route('imports.show', $batch))->assertOk()->assertSee(self::QUOTA_LINE);
    }

    public function test_quota_line_is_hidden_without_an_api_key(): void
    {
        config(['services.youtube.api_key' => '']);
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Searched]);
        ImportItem::factory()->for($batch)->create();

        $this->get(route('dashboard'))->assertOk()->assertDontSee('YouTube quota');
        $this->get(route('imports.review', $batch))->assertOk()->assertDontSee('YouTube quota');
        $this->get(route('imports.show', $batch))->assertOk()->assertDontSee('YouTube quota');
    }

    public function test_quota_line_says_used_up_after_a_quota_error(): void
    {
        Cache::put('youtube:quota_exceeded', true, now()->addHour());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('YouTube quota used up — resets')
            ->assertDontSee(self::QUOTA_LINE);
    }
}
