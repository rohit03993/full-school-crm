<?php

return [

    /*
    | EasyTimePro writes to punch_logs (parallel DB). CRM polls new rows and
    | optional notification_queue if the MySQL trigger from the punch product is installed.
    */
    'punch_table' => env('ATTENDANCE_PUNCH_TABLE', 'punch_logs'),

    'punch_bounce_seconds' => (int) env('ATTENDANCE_PUNCH_BOUNCE_SECONDS', 10),

    'punch_gap_minutes' => (int) env('ATTENDANCE_PUNCH_GAP_MINUTES', 2),

    'auto_out_enabled' => env('ATTENDANCE_AUTO_OUT_ENABLED', true),

    /** Daily auto check-out time (server timezone), e.g. 20:00 = 8 PM. */
    'auto_out_time' => env('ATTENDANCE_AUTO_OUT_TIME', '20:00'),

    /**
     * If a student checks in AFTER auto_out_time (evening class), wait this many
     * minutes from check-in before auto check-out.
     */
    'auto_out_late_grace_minutes' => (int) env('ATTENDANCE_AUTO_OUT_LATE_GRACE_MINUTES', 60),

    'process_batch_size' => (int) env('ATTENDANCE_PUNCH_PROCESS_BATCH', 100),

    /*
    | Student attendance % on profile / counters / marksheets.
    | month_to_date = Present(+Leave) ÷ working days from 1st of month → today
    | (or from batch join date if later). Sundays excluded by default (India coaching).
    */
    'percentage' => [
        'period' => env('ATTENDANCE_PERCENTAGE_PERIOD', 'month_to_date'),
        /** Carbon dayOfWeek values treated as non-working (0 = Sunday). */
        'weekend_days' => array_map(
            'intval',
            array_filter(explode(',', (string) env('ATTENDANCE_WEEKEND_DAYS', '0')), fn (string $v): bool => $v !== ''),
        ) ?: [0],
        /** Count approved Leave toward the numerator (not as absent). */
        'credit_leave' => env('ATTENDANCE_PERCENTAGE_CREDIT_LEAVE', true),
    ],

];
