<?php

namespace App\Enums;

enum OccasionWishType: string
{
    case StudentBirthday = 'student_birthday';
    case StaffBirthday = 'staff_birthday';
    case StaffAnniversary = 'staff_anniversary';
    case DailyReport = 'daily_report';

    public function label(): string
    {
        return match ($this) {
            self::StudentBirthday => 'Student birthday',
            self::StaffBirthday => 'Staff birthday',
            self::StaffAnniversary => 'Staff anniversary',
            self::DailyReport => 'Admin report',
        };
    }

    public function isPersonWish(): bool
    {
        return $this !== self::DailyReport;
    }
}
