<?php

namespace App\Enums;

/**
 * Outcome of one AI agent call recorded in `ai_usage_logs`.
 */
enum AiUsageStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
