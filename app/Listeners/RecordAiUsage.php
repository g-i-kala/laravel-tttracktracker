<?php

namespace App\Listeners;

use App\Enums\AiUsageStatus;
use App\Services\AiUsageService;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\PromptingAgent;

/**
 * Logs every AI agent call (Laravel AI SDK events) to `ai_usage_logs`.
 * Registered as a singleton so the start times survive between the events of one call.
 */
class RecordAiUsage
{
    /**
     * Start times (hrtime, ns) keyed by invocation id.
     *
     * @var array<string, int>
     */
    private array $startedAt = [];

    public function __construct(private AiUsageService $aiUsage) {}

    public function handlePromptingAgent(PromptingAgent $event): void
    {
        $this->startedAt[$event->invocationId] = hrtime(true);
    }

    public function handleAgentPrompted(AgentPrompted $event): void
    {
        $this->aiUsage->record(
            $event->invocationId,
            $event->prompt,
            AiUsageStatus::Succeeded,
            $event->response->usage,
            $this->durationMs($event->invocationId),
        );
    }

    public function handleAgentFailed(AgentFailed $event): void
    {
        $this->aiUsage->record(
            $event->invocationId,
            $event->prompt,
            AiUsageStatus::Failed,
            durationMs: $this->durationMs($event->invocationId),
            errorMessage: $event->exception->getMessage(),
        );
    }

    private function durationMs(string $invocationId): ?int
    {
        $startedAt = $this->startedAt[$invocationId] ?? null;

        unset($this->startedAt[$invocationId]);

        return $startedAt === null ? null : intdiv(hrtime(true) - $startedAt, 1_000_000);
    }
}
