<?php

namespace App\Enums;

enum CampusVisitOutcome: string
{
    case Resolved = 'resolved';
    case NeedsFollowUp = 'needs_follow_up';
    case Referred = 'referred';

    public function label(): string
    {
        return match ($this) {
            self::Resolved => 'Resolved',
            self::NeedsFollowUp => 'Needs follow-up',
            self::Referred => 'Referred (accounts / academics)',
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
