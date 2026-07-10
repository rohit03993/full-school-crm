<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Full CRM backup (database + all uploaded files)
    |--------------------------------------------------------------------------
    |
    | One archive includes: MySQL/SQLite dump, storage/app/private, storage/app/public,
    | APP_KEY snapshot, and a restore checklist. This is disaster recovery — not a report export.
    |
    */

    'disk_path' => storage_path('app/private/backups'),

    /** Keep this many completed backup archives (oldest deleted after a successful run). */
    'retain' => (int) env('CRM_BACKUP_RETAIN', 14),

    /** Daily schedule time (server timezone). */
    'schedule_at' => env('CRM_BACKUP_SCHEDULE_AT', '02:15'),

    /**
     * Relative paths under storage/app/private to skip (temps + the backup folder itself).
     *
     * @var list<string>
     */
    'exclude_private_prefixes' => [
        'backups',
        'livewire-tmp',
        'temp-student-documents',
        'temp-payment-proofs',
        'temp-student-imports',
    ],

    /**
     * Relative paths under storage/app/public to skip.
     *
     * @var list<string>
     */
    'exclude_public_prefixes' => [
        'livewire-tmp',
    ],
];
