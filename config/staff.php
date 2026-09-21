<?php

return [

    /*
    | Staff CRM sessions end at this clock time every day (institute timezone).
    | After this, staff must sign in again with WhatsApp OTP (or password if allowed).
    */
    'daily_logout_time' => env('STAFF_DAILY_LOGOUT_TIME', '20:00'),

    'daily_logout_timezone' => env('STAFF_DAILY_LOGOUT_TIMEZONE', 'Asia/Kolkata'),

];
