<?php

namespace App\Enums;

enum CourseType: string
{
    case Bsc = 'bsc';
    case Diploma = 'diploma';
    case Certificate = 'certificate';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Bsc => 'BSc / Degree',
            self::Diploma => 'Diploma',
            self::Certificate => 'Certificate',
            self::Custom => 'Custom Course',
        };
    }
}
