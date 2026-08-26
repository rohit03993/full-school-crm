<?php

namespace App\Support;

class DemoMode
{
    public static function enabled(): bool
    {
        return filter_var(config('institute.demo_mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function sendBlockedMessage(): string
    {
        return 'Demo desk — WhatsApp is not sent to real phones. The inbox screen still works.';
    }

    public static function bannerMessage(): string
    {
        return 'Task Book demo — dummy XYZ School. Do not enter a paying campus here. WhatsApp is not sent to real phones.';
    }
}
