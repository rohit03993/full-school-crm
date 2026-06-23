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

];
