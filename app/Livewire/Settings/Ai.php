<?php

namespace App\Livewire\Settings;

use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Services\AiSettingsService;
use App\Services\AiUsageService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Settings → AI: this month's AI usage against the budget, recent calls,
 * the API key (stored encrypted, overrides .env), model and monthly budget.
 */
#[Title('AI Settings')]
class Ai extends Component
{
    public const int RECENT_CALLS = 20;

    public string $apiKey = '';

    public string $model = '';

    public string $monthlyBudget = '';

    /**
     * Result of the last key check: `valid`, `invalid`, `unsupported` or null.
     */
    public ?string $keyTestResult = null;

    public function mount(AiSettingsService $aiSettings): void
    {
        $this->model = (string) $aiSettings->model();
        $this->monthlyBudget = $aiSettings->monthlyBudget() === null ? '' : (string) $aiSettings->monthlyBudget();
    }

    public function saveApiKey(AiSettingsService $aiSettings): void
    {
        $this->validate(['apiKey' => ['required', 'string', 'min:20', 'max:500']], [], ['apiKey' => 'API key']);

        $aiSettings->saveApiKey($this->apiKey);

        session()->flash('status', 'API key saved.');

        $this->redirectRoute('settings.ai', navigate: true);
    }

    public function removeApiKey(AiSettingsService $aiSettings): void
    {
        $aiSettings->removeApiKey();

        session()->flash('status', 'Saved API key removed.');

        $this->redirectRoute('settings.ai', navigate: true);
    }

    public function testApiKey(AiSettingsService $aiSettings): void
    {
        $this->keyTestResult = match ($aiSettings->testApiKey()) {
            true => 'valid',
            false => 'invalid',
            null => 'unsupported',
        };
    }

    public function saveSettings(AiSettingsService $aiSettings): void
    {
        $validated = $this->validate([
            'model' => ['required', 'string', 'max:100'],
            'monthlyBudget' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ], [], ['monthlyBudget' => 'monthly budget']);

        $aiSettings->saveModelAndBudget(
            $validated['model'],
            filled($validated['monthlyBudget']) ? (float) $validated['monthlyBudget'] : null,
        );

        session()->flash('status', 'AI settings saved.');

        $this->redirectRoute('settings.ai', navigate: true);
    }

    /**
     * @return array{spent: float, budget: ?float, usage: ?float, calls: int, failed: int, inputTokens: int, outputTokens: int}
     */
    #[Computed]
    public function thisMonth(): array
    {
        $aiSettings = app(AiSettingsService::class);
        $logs = AiUsageLog::where('created_at', '>=', now()->startOfMonth());

        return [
            'spent' => app(AiUsageService::class)->spentThisMonth(),
            'budget' => $aiSettings->monthlyBudget(),
            'usage' => $aiSettings->budgetUsage(),
            'calls' => (clone $logs)->count(),
            'failed' => (clone $logs)->where('status', AiUsageStatus::Failed)->count(),
            'inputTokens' => (int) (clone $logs)->sum('input_tokens'),
            'outputTokens' => (int) (clone $logs)->sum('output_tokens'),
        ];
    }

    /**
     * @return Collection<int, AiUsageLog>
     */
    #[Computed]
    public function recentCalls(): Collection
    {
        return AiUsageLog::with('importBatch')->latest()->latest('id')->limit(self::RECENT_CALLS)->get();
    }

    /**
     * @return array{source: ?string, masked: ?string, provider: string}
     */
    #[Computed]
    public function key(): array
    {
        $aiSettings = app(AiSettingsService::class);

        return [
            'source' => $aiSettings->apiKeySource(),
            'masked' => $aiSettings->maskedApiKey(),
            'provider' => $aiSettings->provider(),
        ];
    }

    /**
     * Models with a price in config, suggested in the model field.
     *
     * @return list<string>
     */
    #[Computed]
    public function pricedModels(): array
    {
        return array_keys(config('services.ai_pricing.'.app(AiSettingsService::class)->provider(), []));
    }

    public function render(): View
    {
        return view('livewire.settings.ai');
    }
}
