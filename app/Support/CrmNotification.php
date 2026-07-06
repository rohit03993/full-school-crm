<?php

namespace App\Support;

use Filament\Notifications\Notification;

class CrmNotification
{
    public static function sendOutcome(string $title, string $body, bool $success, bool $warningOnFailure = false): void
    {
        $notification = Notification::make()->title($title)->body($body);

        if ($success) {
            $notification->success();
        } elseif ($warningOnFailure) {
            $notification->warning();
        } else {
            $notification->danger();
        }

        $notification->send();
    }
}
