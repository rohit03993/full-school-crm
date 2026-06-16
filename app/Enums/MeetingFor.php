<?php

namespace App\Enums;

enum MeetingFor: string
{
    case FolksIndia = 'folks_india';
    case EnglishCoffee = 'english_coffee';

    public function label(): string
    {
        return match ($this) {
            self::FolksIndia => 'Folks India',
            self::EnglishCoffee => 'English Coffee',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FolksIndia => 'heroicon-m-building-library',
            self::EnglishCoffee => 'heroicon-m-language',
        };
    }

    /**
     * @return array{bg: string, text: string, ring: string}
     */
    public function badgeColors(): array
    {
        return match ($this) {
            self::FolksIndia => [
                'bg' => 'bg-amber-500/20',
                'text' => 'text-amber-900 dark:text-amber-300',
                'ring' => 'ring-amber-500/35',
            ],
            self::EnglishCoffee => [
                'bg' => 'bg-violet-500/20',
                'text' => 'text-violet-900 dark:text-violet-300',
                'ring' => 'ring-violet-500/35',
            ],
        };
    }
}
