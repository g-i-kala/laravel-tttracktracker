<?php

namespace App\Livewire\Settings;

use App\Services\YoutubeQuotaService;
use App\Services\YoutubeSearchService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Settings → YouTube: today's estimated quota use and whether the API key is set.
 */
#[Title('YouTube Settings')]
class Youtube extends Component
{
    /**
     * @return array{used: int, limit: int, remaining: int, searchesLeft: int, usage: float, isUsedUp: bool, resetsAt: CarbonImmutable}
     */
    #[Computed]
    public function quota(): array
    {
        return app(YoutubeQuotaService::class)->today();
    }

    #[Computed]
    public function hasApiKey(): bool
    {
        return app(YoutubeSearchService::class)->isEnabled();
    }

    public function render(): View
    {
        return view('livewire.settings.youtube');
    }
}
