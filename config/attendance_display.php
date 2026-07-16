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

    /** Browser poll interval for new punches (milliseconds). */
    'poll_interval_ms' => (int) env('ATTENDANCE_DISPLAY_POLL_MS', 2500),

    /** Signed student-photo URLs lifetime (minutes). */
    'photo_url_ttl_minutes' => (int) env('ATTENDANCE_DISPLAY_PHOTO_TTL', 360),

];
