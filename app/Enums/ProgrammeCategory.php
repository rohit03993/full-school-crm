<?php

namespace App\Enums;

enum ProgrammeCategory: string
{
    case School = 'school';
    case Coaching = 'coaching';
    case College = 'college';
    case Certificate = 'certificate';
    case Hospitality = 'hospitality';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::Coaching => 'Coaching',
            self::College => 'College',
            self::Certificate => 'Certificate',
            self::Hospitality => 'Hotel Management',
            self::Custom => 'Custom',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::School => 'heroicon-m-building-library',
            self::Coaching => 'heroicon-m-academic-cap',
            self::College => 'heroicon-m-building-office-2',
            self::Certificate => 'heroicon-m-document-check',
            self::Hospitality => 'heroicon-m-building-storefront',
            self::Custom => 'heroicon-m-squares-2x2',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::School => 'warning',
            self::Coaching => 'purple',
            self::College => 'info',
            self::Certificate => 'success',
            self::Hospitality => 'amber',
            self::Custom => 'gray',
        };
    }
}
