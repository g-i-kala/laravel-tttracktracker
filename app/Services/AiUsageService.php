<?php

namespace App\Services;

use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Records AI agent calls (tokens, estimated cost) in `ai_usage_logs`.
 * Prices per 1M tokens come from `config('services.ai_pricing')`.
 */
class AiUsageService
{
    /**
     * Context key that links AI calls to the import batch being processed.
     */
    public const string BATCH_CONTEXT_KEY = 'import_batch_id';

    public function record(string $invocationId, AgentPrompt $prompt, AiUsageStatus $status, ?Usage $usage = null, ?int $durationMs = null, ?string $errorMessage = null): AiUsageLog
    {
        $provider = $prompt->provider->name();
        $cachedInputTokens = $usage instanceof TextUsage ? (int) $usage->cacheReadInputTokens : 0;

        return AiUsageLog::create([
            'import_batch_id' => Context::get(self::BATCH_CONTEXT_KEY),
            'invocation_id' => $invocationId,
            'agent' => $prompt->agent::class,
            'provider' => $provider,
            'model' => $prompt->model,
            'input_tokens' => $usage?->inputTokens ?? 0,
            'cached_input_tokens' => $cachedInputTokens,
            'output_tokens' => $usage?->outputTokens ?? 0,
            'cost_usd' => $this->estimateCost($provider, $prompt->model, $usage?->inputTokens ?? 0, $cachedInputTokens, $usage?->outputTokens ?? 0),
            'duration_ms' => $durationMs,
            'status' => $status,
            'error_message' => $errorMessage === null ? null : Str::limit($errorMessage, 1000),
        ]);
    }

    /**
     * Estimated cost in USD of all calls since the start of the current month (app timezone).
     */
    public function spentThisMonth(): float
    {
        return (float) AiUsageLog::where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
    }

    /**
     * Estimated cost in USD, or null when the model has no price in config.
     * Cached input tokens are part of the input tokens and use the cheaper cached price when set.
     */
    public function estimateCost(string $provider, string $model, int $inputTokens, int $cachedInputTokens, int $outputTokens): ?float
    {
        // Model names contain dots (gpt-4.1-mini), so they cannot be part of a config() dot path.
        $prices = config("services.ai_pricing.{$provider}", [])[$model] ?? null;

        if (! is_array($prices)) {
            return null;
        }

        $cachedPrice = $prices['cached_input'] ?? $prices['input'];

        $cost = ($inputTokens - $cachedInputTokens) * $prices['input']
            + $cachedInputTokens * $cachedPrice
            + $outputTokens * $prices['output'];

        return round($cost / 1_000_000, 6);
    }
}
