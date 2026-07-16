<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reception / TV attendance display
    |--------------------------------------------------------------------------
    |
    | Read-only screen that shows the latest IN/OUT punch with student photo.
    | Does not write attendance — only reads punch_logs (machine + manual).
    |
    */

    /** How long a punch card stays on screen (milliseconds) in the browser. */
    'card_duration_ms' => (int) env('ATTENDANCE_DISPLAY_CARD_MS', 10000),

    /** Fast poll for new punches + latest-10 list (milliseconds). */
    'poll_interval_ms' => (int) env('ATTENDANCE_DISPLAY_POLL_MS', 2000),

    /** Slower poll for class-wise stats grid (milliseconds). */
    'summary_poll_interval_ms' => (int) env('ATTENDANCE_DISPLAY_SUMMARY_POLL_MS', 15000),

    /** Server cache for class-wise summary (seconds). */
    'summary_cache_seconds' => (int) env('ATTENDANCE_DISPLAY_SUMMARY_CACHE', 15),

    /** Max punch rows scanned per live poll (today only). */
    'live_punch_scan_limit' => (int) env('ATTENDANCE_DISPLAY_LIVE_SCAN', 20),

    /** Signed student-photo URLs lifetime (minutes). */
    'photo_url_ttl_minutes' => (int) env('ATTENDANCE_DISPLAY_PHOTO_TTL', 360),

];
