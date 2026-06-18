<?php

namespace App\Enums;

enum MeetingFor: string
{
    case School = 'school';
    case Coaching = 'coaching';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::Coaching => 'Coaching',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::School => 'heroicon-m-building-library',
            self::Coaching => 'heroicon-m-academic-cap',
        };
    }

    /**
     * @return array{bg: string, text: string, ring: string}
     */
    public function badgeColors(): array
    {
        return match ($this) {
            self::School => [
                'bg' => 'bg-amber-500/20',
                'text' => 'text-amber-900 dark:text-amber-300',
                'ring' => 'ring-amber-500/35',
            ],
            self::Coaching => [
                'bg' => 'bg-violet-500/20',
                'text' => 'text-violet-900 dark:text-violet-300',
                'ring' => 'ring-violet-500/35',
            ],
        };
    }
}
