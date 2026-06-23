<?php

namespace App\Enums;

enum LeadSource: string
{
    case Website = 'website';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Google = 'google';
    case WalkIn = 'walk_in';
    case StudentReference = 'student_reference';
    case Seminar = 'seminar';
    case Banner = 'banner';
    case Newspaper = 'newspaper';
    case BulkImport = 'bulk_import';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Google => 'Google',
            self::WalkIn => 'Walk-in',
            self::StudentReference => 'Student Reference',
            self::Seminar => 'Seminar',
            self::Banner => 'Banner',
            self::Newspaper => 'Newspaper',
            self::BulkImport => 'Bulk Import',
            self::Other => 'Other',
        };
    }
}
