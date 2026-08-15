<?php

return [
    /*
    | Web Push (PWA) — one institute app, subscriptions for staff and portal.
    | Generate keys: php artisan crm:webpush-vapid
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'enabled' => (bool) env('WEBPUSH_ENABLED', true),
];
