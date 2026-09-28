<?php

namespace App\Enums;

enum SectionCoursePlanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case SentBack = 'sent_back';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::SentBack => 'Sent back',
            self::Final => 'Final',
        };
    }
}
