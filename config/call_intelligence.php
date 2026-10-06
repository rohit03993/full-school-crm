<?php

return [

    'enabled' => env('CALL_INTELLIGENCE_ENABLED', false),

    'api_url' => env('CALL_INTELLIGENCE_API_URL'),

    'school_code' => env('CALL_INTELLIGENCE_SCHOOL_CODE'),

    'school_secret' => env('CALL_INTELLIGENCE_SCHOOL_SECRET'),

    'callback_secret' => env('CALL_INTELLIGENCE_CALLBACK_SECRET'),

    'timeout_seconds' => (int) env('CALL_INTELLIGENCE_TIMEOUT_SECONDS', 180),

];
