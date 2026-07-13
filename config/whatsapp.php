<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Campaign batch processing
    |--------------------------------------------------------------------------
    */

    'batch_size' => (int) env('WHATSAPP_CAMPAIGN_BATCH_SIZE', 10),

    'next_batch_delay_seconds' => (int) env('WHATSAPP_CAMPAIGN_BATCH_DELAY', 2),

    'pause_after_messages' => (int) env('WHATSAPP_CAMPAIGN_PAUSE_AFTER', 0),

    'pause_seconds' => (float) env('WHATSAPP_CAMPAIGN_PAUSE_SECONDS', 0),

    /*
    |--------------------------------------------------------------------------
    | Inline campaign processing (no queue worker required)
    |--------------------------------------------------------------------------
    |
    | Campaigns with at most this many recipients run immediately in the
    | current request (attendance punch, post-call, small bulk sends).
    |
    */

    'inline_campaign_recipient_limit' => (int) env('WHATSAPP_INLINE_CAMPAIGN_LIMIT', 50),

    /*
    |--------------------------------------------------------------------------
    | Homework WhatsApp (Meta live campaign name)
    |--------------------------------------------------------------------------
    |
    | Template should accept: student name, roll number, homework title, portal link.
    */

    'homework_template_name' => env('WHATSAPP_HOMEWORK_TEMPLATE', 'homework_api'),

    'integration_api_key' => env('WHATSAPP_INTEGRATION_API_KEY'),

];
