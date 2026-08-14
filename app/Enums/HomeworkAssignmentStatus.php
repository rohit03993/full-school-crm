<?php

namespace App\Enums;

enum HomeworkAssignmentStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted to admin',
            self::Approved => 'Approved',
            self::Sent => 'Sent to parents',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Approved => 'info',
            self::Sent => 'success',
        };
    }
}
