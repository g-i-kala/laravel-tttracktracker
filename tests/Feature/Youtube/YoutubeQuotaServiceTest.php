<?php

namespace Tests\Feature\Youtube;

use App\Models\YoutubeApiCall;
use App\Services\YoutubeQuotaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class YoutubeQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string PACIFIC = 'America/Los_Angeles';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.youtube.daily_quota' => 10000]);
        $this->travelTo(CarbonImmutable::parse('2026-10-17 01:00', self::PACIFIC));
    }

    public function test_counts_only_calls_since_midnight_pacific_time(): void
    {
        $this->callAt('2026-10-16 23:59', 100);
        $this->callAt('2026-10-17 00:01', 100);
        $this->callAt('2026-10-17 00:30', 100);
        YoutubeApiCall::factory()->quotaExceeded()->create(['created_at' => $this->pacific('2026-10-17 00:40')]);

        $quota = app(YoutubeQuotaService::class)->today();

        $this->assertSame(200, $quota['used']);
        $this->assertSame(10000, $quota['limit']);
        $this->assertSame(9800, $quota['remaining']);
        $this->assertSame(98, $quota['searchesLeft']);
        $this->assertEqualsWithDelta(0.02, $quota['usage'], 0.0001);
        $this->assertFalse($quota['isUsedUp']);
    }

    public function test_resets_at_next_midnight_pacific_time_in_the_app_timezone(): void
    {
        $resetsAt = app(YoutubeQuotaService::class)->today()['resetsAt'];

        $this->assertTrue($resetsAt->equalTo($this->pacific('2026-10-18 00:00')));
        $this->assertSame(config('app.timezone'), $resetsAt->timezoneName);
    }

    public function test_daily_limit_comes_from_config(): void
    {
        config(['services.youtube.daily_quota' => 1000]);
        $this->callAt('2026-10-17 00:10', 100);

        $quota = app(YoutubeQuotaService::class)->today();

        $this->assertSame(1000, $quota['limit']);
        $this->assertSame(9, $quota['searchesLeft']);
    }

    public function test_is_used_up_when_no_full_search_is_left(): void
    {
        config(['services.youtube.daily_quota' => 250]);
        $this->callAt('2026-10-17 00:10', 100);
        $this->callAt('2026-10-17 00:20', 100);

        $quota = app(YoutubeQuotaService::class)->today();

        $this->assertSame(0, $quota['searchesLeft']);
        $this->assertTrue($quota['isUsedUp']);
    }

    public function test_is_used_up_after_a_quota_error_even_below_the_estimate(): void
    {
        Cache::put('youtube:quota_exceeded', true, now()->addHour());

        $quota = app(YoutubeQuotaService::class)->today();

        $this->assertSame(0, $quota['used']);
        $this->assertTrue($quota['isUsedUp']);
    }

    private function callAt(string $pacificTime, int $units): void
    {
        YoutubeApiCall::factory()->create(['quota_units' => $units, 'created_at' => $this->pacific($pacificTime)]);
    }

    /**
     * Pacific time converted to the app timezone, the way timestamps are stored.
     */
    private function pacific(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, self::PACIFIC)->setTimezone(config('app.timezone'));
    }
}
