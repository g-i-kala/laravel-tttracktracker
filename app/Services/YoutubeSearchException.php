<?php

namespace App\Services;

use RuntimeException;
use Throwable;

/**
 * A YouTube Data API search failed. The caller falls back to no-key mode for that item.
 */
class YoutubeSearchException extends RuntimeException
{
    /**
     * API error reasons that mean the daily quota (or a rate limit) is used up.
     */
    private const array QUOTA_REASONS = ['quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded', 'userRateLimitExceeded'];

    public function __construct(string $message, public readonly ?string $reason = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isQuotaExceeded(): bool
    {
        return in_array($this->reason, self::QUOTA_REASONS, true);
    }
}
