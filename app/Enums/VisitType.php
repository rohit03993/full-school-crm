<?php

namespace App\Enums;

enum VisitType: string
{
    case FirstVisit = 'first_visit';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::FirstVisit => 'First Visit',
            self::FollowUp => 'Follow-up Visit',
        };
    }
}
