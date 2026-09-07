<?php

namespace App\Enums;

enum WhatsAppContactKind: string
{
    case Staff = 'staff';
    case Student = 'student';
    case Lead = 'lead';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Student => 'Student',
            self::Lead => 'Lead',
            self::Unknown => 'Unknown',
        };
    }
}
