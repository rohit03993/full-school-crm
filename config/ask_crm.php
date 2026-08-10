<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ask CRM — Gemini (optional natural-language parsing)
    |--------------------------------------------------------------------------
    |
    | When enabled, Gemini parses intent + student name from messy English/Hinglish.
    | CRM data is always fetched from the database — AI never invents answers.
    |
    | Get a key: https://aistudio.google.com/apikey
    |
    */

    'use_ai' => env('ASK_CRM_USE_AI', false),

    'gemini_api_key' => env('GEMINI_API_KEY'),

    'gemini_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    'gemini_timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 15),

];
