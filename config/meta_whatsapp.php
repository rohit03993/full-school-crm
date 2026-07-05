<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Direct Meta integration (parallel to Pal Digital / waservice).
    | Credentials are usually stored in the database via Setup → Meta WhatsApp.
    |
    */

    'graph_version' => env('META_WHATSAPP_GRAPH_VERSION', 'v20.0'),

    'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),

    'waba_id' => env('META_WHATSAPP_WABA_ID'),

    'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),

    'default_language' => env('META_WHATSAPP_DEFAULT_LANGUAGE', 'en'),

    'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),

    'app_secret' => env('META_WHATSAPP_APP_SECRET'),

];
