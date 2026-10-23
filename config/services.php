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
        // Backend selector: "vertex" routes through Vertex AI (higher quota, OAuth auth),
        // "ai_studio" uses the AI Studio API key endpoint. Default ai_studio for back-compat.
        'backend' => env('GEMINI_BACKEND', 'ai_studio'),
        'vertex' => [
            'project_id' => env('GOOGLE_CLOUD_PROJECT'),
            'region' => env('GOOGLE_CLOUD_REGION', 'asia-southeast1'),
            // Path to service account JSON. Absolute, or relative to base_path().
            // When set, the parser exports it as GOOGLE_APPLICATION_CREDENTIALS for google/auth.
            'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        ],
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
        'base_url' => env('OLLAMA_BASE_URL', 'https://ollama.com'),
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('OLLAMA_ALLOWED_ORIGINS', 'http://127.0.0.1:11434,http://localhost:11434,http://host.docker.internal:11434,https://ollama.com')),
        ))),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
        'vision_model' => env('OLLAMA_VISION_MODEL', 'llama3.2-vision'),
        'audio_model' => env('OLLAMA_AUDIO_MODEL', 'nemotron3'),
        'api_key' => env('OLLAMA_API_KEY'),
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
