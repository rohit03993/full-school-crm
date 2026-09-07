<?php

namespace App\Enums;

enum WhatsAppSendActor: string
{
    case Staff = 'staff';
    case Automatic = 'automatic';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Automatic => 'Automatic',
            self::System => 'System',
        };
    }
}
