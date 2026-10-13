<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiManager;
use Throwable;

/**
 * AI settings for track list parsing: API key (Settings first, then .env),
 * model and monthly budget. Edited on the Settings → AI screen.
 */
class AiSettingsService
{
    public const string API_KEY = 'ai.api_key';

    public const string MODEL = 'ai.model';

    public const string MONTHLY_BUDGET = 'ai.monthly_budget_usd';

    /**
     * Share of the budget at which a warning is shown.
     */
    public const float WARNING_THRESHOLD = 0.8;

    public function __construct(private SettingsService $settings, private AiUsageService $aiUsage) {}

    public function provider(): string
    {
        return config('services.track_list_ai.provider');
    }

    public function apiKey(): ?string
    {
        return $this->settings->getSecret(self::API_KEY) ?? $this->envApiKey();
    }

    /**
     * Where the active key comes from: `settings`, `env`, or null when there is none.
     */
    public function apiKeySource(): ?string
    {
        return match (true) {
            filled($this->settings->getSecret(self::API_KEY)) => 'settings',
            filled($this->envApiKey()) => 'env',
            default => null,
        };
    }

    /**
     * The active key with only its start and last 4 characters visible, e.g. `sk-…a1b2`.
     */
    public function maskedApiKey(): ?string
    {
        $key = $this->apiKey();

        if (blank($key)) {
            return null;
        }

        return substr($key, 0, 3).'…'.substr($key, -4);
    }

    public function saveApiKey(string $apiKey): void
    {
        $this->settings->putSecret(self::API_KEY, trim($apiKey));
    }

    public function removeApiKey(): void
    {
        $this->settings->forget(self::API_KEY);
    }

    /**
     * Run the callback with the active key in the provider config, then restore it.
     * The provider instance is rebuilt, because the queue worker caches it with the old key.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withApiKey(callable $callback): mixed
    {
        $configKey = 'ai.providers.'.$this->provider().'.key';
        $envApiKey = config($configKey);
        $aiManager = app(AiManager::class);

        config([$configKey => $this->apiKey()]);
        $aiManager->forgetInstance($this->provider());

        try {
            return $callback();
        } finally {
            config([$configKey => $envApiKey]);
            $aiManager->forgetInstance($this->provider());
        }
    }

    /**
     * Check a key against the provider without spending tokens (lists models).
     * Returns null when the provider has no check.
     */
    public function testApiKey(?string $apiKey = null): ?bool
    {
        $apiKey ??= $this->apiKey();

        if ($this->provider() !== 'openai') {
            return null;
        }

        if (blank($apiKey)) {
            return false;
        }

        try {
            return Http::withToken($apiKey)->timeout(10)->get('https://api.openai.com/v1/models')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function model(): ?string
    {
        return $this->settings->get(self::MODEL) ?? config('services.track_list_ai.model');
    }

    public function monthlyBudget(): ?float
    {
        $budget = $this->settings->get(self::MONTHLY_BUDGET);

        return $budget === null ? null : (float) $budget;
    }

    public function saveModelAndBudget(string $model, ?float $monthlyBudget): void
    {
        $this->settings->put(self::MODEL, trim($model));
        $this->settings->put(self::MONTHLY_BUDGET, $monthlyBudget === null ? null : (string) $monthlyBudget);
    }

    /**
     * Spent this month as a share of the budget (1.0 = reached), or null without a budget.
     */
    public function budgetUsage(): ?float
    {
        $budget = $this->monthlyBudget();

        if ($budget === null) {
            return null;
        }

        if ($budget <= 0) {
            return 1.0;
        }

        return $this->aiUsage->spentThisMonth() / $budget;
    }

    public function isBudgetReached(): bool
    {
        return ($this->budgetUsage() ?? 0) >= 1;
    }

    public function isBudgetNearlyReached(): bool
    {
        $usage = $this->budgetUsage() ?? 0;

        return $usage >= self::WARNING_THRESHOLD && $usage < 1;
    }

    private function envApiKey(): ?string
    {
        return config('ai.providers.'.$this->provider().'.key');
    }
}
