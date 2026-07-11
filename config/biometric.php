<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ZKTeco / ADMS push protocol (CRM acts as cloud ADMS server)
    |--------------------------------------------------------------------------
    |
    | Devices POST attendance to /iclock/*. Unknown serials are rejected.
    | Raw punches are stored, then mirrored into punch_logs for the existing
    | PunchAttendanceProcessor (attendance math is unchanged).
    |
    */

    'enabled' => env('BIOMETRIC_ADMS_ENABLED', true),

    'route_prefix' => env('BIOMETRIC_ADMS_PREFIX', 'iclock'),

    /** Require device serial to exist and be active in biometric_devices. */
    'require_allowlist' => env('BIOMETRIC_ADMS_REQUIRE_ALLOWLIST', true),

    /** After saving raw + punch_logs, run processPending immediately. */
    'process_inline' => env('BIOMETRIC_ADMS_PROCESS_INLINE', true),

    /**
     * IANA zone used when parsing punch timestamps from the device
     * (device sends local wall-clock times, not UTC).
     */
    'timezone' => env('BIOMETRIC_ADMS_TIMEZONE', env('APP_TIMEZONE', 'Asia/Kolkata')),

    /**
     * ADMS handshake TimeZone value: minutes east of UTC.
     * K40 Pro ignores names like Asia/Kolkata — use 330 for India.
     * Leave null to compute from biometric.timezone.
     */
    'timezone_offset_minutes' => env('BIOMETRIC_ADMS_TZ_OFFSET_MINUTES'),

];
