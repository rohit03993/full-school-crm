<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Leave = 'leave';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Leave => 'Leave',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Absent => 'A',
            self::Leave => 'L',
        };
    }
}
