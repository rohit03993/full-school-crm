<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Face Verify service integration
    |--------------------------------------------------------------------------
    |
    | Face Verify owns the Android kiosk and face AI verification. The CRM
    | remains the source of truth for students and final attendance.
    |
    */

    'enabled' => env('FACE_VERIFY_ENABLED', false),

    'api_url' => env('FACE_VERIFY_API_URL'),

    'service_token' => env('FACE_VERIFY_SERVICE_TOKEN'),

    'callback_secret' => env('FACE_VERIFY_CALLBACK_SECRET'),

    'default_device_id' => env('FACE_VERIFY_DEFAULT_DEVICE_ID'),

    'timeout_seconds' => (int) env('FACE_VERIFY_TIMEOUT_SECONDS', 30),

    'http_timeout_seconds' => (int) env('FACE_VERIFY_HTTP_TIMEOUT_SECONDS', 10),

];
