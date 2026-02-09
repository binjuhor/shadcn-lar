<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'exchangerate_api' => [
        'key' => env('EXCHANGERATE_API_KEY'),
        'base' => env('EXCHANGERATE_API_BASE', 'USD'),
    ],

    'open_exchange_rates' => [
        'key' => env('OPEN_EXCHANGE_RATES_KEY'),
        'base' => env('OPEN_EXCHANGE_RATES_BASE', 'USD'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
    ],

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
        'vision_model' => env('OLLAMA_VISION_MODEL', 'llama3.2-vision'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
        'vision_model' => env('OPENROUTER_VISION_MODEL', 'openrouter/auto'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
    ],

    'smart_input' => [
        'provider' => env('SMART_INPUT_PROVIDER', 'deepseek'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI'),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

];
