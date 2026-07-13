<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Direct Meta Cloud API integration.
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

    /*
    |--------------------------------------------------------------------------
    | India per-message rates (INR) — Meta WhatsApp pricing fallback
    |--------------------------------------------------------------------------
    |
    | Used when estimating cost on each logged message. Analytics also pulls
    | official totals from Meta pricing_analytics when WABA ID is configured.
    | Override per institute via settings (meta_whatsapp.pricing_rates).
    |
    */
    'pricing_currency' => env('META_WHATSAPP_PRICING_CURRENCY', 'INR'),

    'pricing_rates_inr' => [
        'MARKETING' => (float) env('META_WHATSAPP_RATE_MARKETING_INR', 0.7846),
        'UTILITY' => (float) env('META_WHATSAPP_RATE_UTILITY_INR', 0.3500),
        'AUTHENTICATION' => (float) env('META_WHATSAPP_RATE_AUTH_INR', 0.3500),
        'AUTHENTICATION_INTERNATIONAL' => (float) env('META_WHATSAPP_RATE_AUTH_INTL_INR', 2.3000),
        'SERVICE' => (float) env('META_WHATSAPP_RATE_SERVICE_INR', 0.0000),
        'UNKNOWN' => (float) env('META_WHATSAPP_RATE_UNKNOWN_INR', 0.3500),
    ],

];
