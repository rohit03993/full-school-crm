<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Which company answers AI jobs
    |--------------------------------------------------------------------------
    |
    | gemini uses GEMINI_API_KEY (the same key as Ask CRM).
    | openai uses OPENAI_API_KEY. The homework screen stays the same.
    |
    */

    'provider' => env('AI_PROVIDER', 'gemini'),

    'homework' => [
        'enabled' => filter_var(env('HOMEWORK_AI_ENABLED', true), FILTER_VALIDATE_BOOL),
        'daily_limit' => (int) env('HOMEWORK_AI_DAILY_LIMIT', 10),
        'tries_per_open' => (int) env('HOMEWORK_AI_TRIES_PER_OPEN', 3),
        'title_max' => 255,
        'description_max' => 4000,
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('HOMEWORK_AI_MODEL', 'gemini-3.5-flash-lite'),
        'timeout' => (int) env('GEMINI_TIMEOUT_SECONDS', 20),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('HOMEWORK_AI_OPENAI_MODEL', 'gpt-4.1-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT_SECONDS', 20),
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    ],

];
