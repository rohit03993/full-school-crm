<?php

namespace App\Support;

class PunchWhatsAppOutcome
{
    /**
     * @return array{queued: bool, message: string}
     */
    public static function queued(string $message = 'Parent WhatsApp queued for delivery.'): array
    {
        return ['queued' => true, 'message' => $message];
    }

    /**
     * @return array{queued: bool, message: string}
     */
    public static function skipped(string $message): array
    {
        return ['queued' => false, 'message' => $message];
    }
}
