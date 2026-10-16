<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, TokenomixAI, and RAR command binary.
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

    'tokenomix' => [
        'base_url' => env('TOKENOMIXAI_BASE_URL', 'https://api.tokenomixai.com/v1'),
        'api_key' => env('TOKENOMIXAI_API_KEY'),
        // First retry delay (seconds) for 502/429/dropped connections; doubles each attempt
        'retry_base_seconds' => (int) env('TOKENOMIXAI_RETRY_BASE_SECONDS', 2),
        // Output token limit for chat/agent replies (0 = provider default)
        'chat_max_tokens' => (int) env('TOKENOMIXAI_CHAT_MAX_TOKENS', 16384),
        // Tool-call rounds per chat request before the browser auto-continues
        'agent_max_steps' => (int) env('TOKENOMIXAI_AGENT_MAX_STEPS', 50),
        // Characters of earlier chat sent with each request (older messages are shortened/dropped)
        'history_char_budget' => (int) env('TOKENOMIXAI_HISTORY_CHAR_BUDGET', 160000),
        // GitHub Actions monitoring: max wait per tool call, poll interval, and how long to wait for a run to appear
        'ci_wait_seconds' => (int) env('XZY_CI_WAIT_SECONDS', 900),
        'ci_poll_seconds' => (int) env('XZY_CI_POLL_SECONDS', 10),
        'ci_appear_seconds' => (int) env('XZY_CI_APPEAR_SECONDS', 90),
    ],

    'rar' => [
        'binary_path' => env('RAR_BINARY_PATH'),
    ],

];
