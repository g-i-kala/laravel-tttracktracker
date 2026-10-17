<?php

namespace App\Enums;

/**
 * Outcome of one YouTube Data API call recorded in `youtube_api_calls`.
 */
enum YoutubeApiCallStatus: string
{
    case Succeeded = 'succeeded';
    case QuotaExceeded = 'quota_exceeded';
    case Failed = 'failed';
}
