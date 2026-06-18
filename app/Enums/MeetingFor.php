<?php

namespace App\Enums;

enum MeetingFor: string
{
    case School = 'school';
    case Coaching = 'coaching';
    case College = 'college';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::Coaching => 'Coaching',
            self::College => 'College',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::School => 'heroicon-m-building-library',
            self::Coaching => 'heroicon-m-academic-cap',
            self::College => 'heroicon-m-building-office-2',
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
            self::College => [
                'bg' => 'bg-sky-500/20',
                'text' => 'text-sky-900 dark:text-sky-300',
                'ring' => 'ring-sky-500/35',
            ],
        };
    }
}
