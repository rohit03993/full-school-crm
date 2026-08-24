<?php

namespace App\Enums;

enum FeeReminderStage: string
{
    case Upcoming = 'upcoming';
    case Due = 'due';
    case Overdue = 'overdue';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming (before due date)',
            self::Due => 'Due today',
            self::Overdue => 'Overdue',
            self::Manual => 'Sent by staff',
        };
    }
}
