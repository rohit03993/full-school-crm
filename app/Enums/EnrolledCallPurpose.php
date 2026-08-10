<?php

namespace App\Enums;

enum EnrolledCallPurpose: string
{
    case FeeQuery = 'fee_query';
    case Attendance = 'attendance';
    case Documents = 'documents';
    case Academic = 'academic';
    case Complaint = 'complaint';
    case General = 'general';
    case CallbackNeeded = 'callback_needed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::FeeQuery => 'Fee query',
            self::Attendance => 'Attendance',
            self::Documents => 'Documents',
            self::Academic => 'Academic',
            self::Complaint => 'Complaint',
            self::General => 'General',
            self::CallbackNeeded => 'Callback needed',
            self::Resolved => 'Resolved',
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
