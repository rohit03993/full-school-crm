<?php

namespace App\Enums;

enum EnrolledCallQuickTag: string
{
    case FeeDue = 'fee_due';
    case Attendance = 'attendance';
    case Documents = 'documents';
    case Timetable = 'timetable';
    case Complaint = 'complaint';
    case Homework = 'homework';
    case Academic = 'academic';

    public function label(): string
    {
        return match ($this) {
            self::FeeDue => 'Fee due',
            self::Attendance => 'Attendance',
            self::Documents => 'Docs',
            self::Timetable => 'Timetable',
            self::Complaint => 'Complaint',
            self::Homework => 'Homework',
            self::Academic => 'Academic',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
