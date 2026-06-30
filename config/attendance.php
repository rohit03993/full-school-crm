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

    'auto_out_time' => env('ATTENDANCE_AUTO_OUT_TIME', '19:00'),

    'process_batch_size' => (int) env('ATTENDANCE_PUNCH_PROCESS_BATCH', 100),

];
