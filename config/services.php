<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_DATA_API_KEY'),
        'auto_include_threshold' => (int) env('YOUTUBE_AUTO_INCLUDE_THRESHOLD', 80),
        'preselect_threshold' => (int) env('YOUTUBE_PRESELECT_THRESHOLD', 60),
        'candidates_limit' => (int) env('YOUTUBE_CANDIDATES_LIMIT', 3),
        'cache_ttl_days' => (int) env('YOUTUBE_CACHE_TTL_DAYS', 30),
        'daily_quota' => (int) env('YOUTUBE_DAILY_QUOTA', 10000),
    ],

    /*
    | AI track list parsing (Laravel AI SDK). Enabled when the provider in
    | config/ai.php has an API key (e.g. OPENAI_API_KEY). An empty model
    | uses the provider's default text model.
    */
    'track_list_ai' => [
        'provider' => env('AI_PARSER_PROVIDER', 'openai'),
        'model' => env('AI_PARSER_MODEL') ?: null,
        'timeout' => (int) env('AI_PARSER_TIMEOUT', 180),
    ],

    /*
    | AI model prices in USD per 1M tokens, used to estimate the cost of each
    | call in ai_usage_logs. Models without a price are logged with no cost.
    | Check the provider's pricing page when adding or changing a model.
    */
    'ai_pricing' => [
        'openai' => [
            'gpt-4.1-mini' => ['input' => 0.40, 'cached_input' => 0.10, 'output' => 1.60],
        ],
    ],

];
