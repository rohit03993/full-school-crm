<?php

namespace App\Enums;

enum NumberSequenceType: string
{
    case Enquiry = 'enquiry';
    case Admission = 'admission';
    case Enrollment = 'enrollment';
    case Receipt = 'receipt';

    public function prefix(): string
    {
        $base = (string) config('institute.number_prefix', 'CRM');

        return match ($this) {
            self::Enquiry => "{$base}-ENQ",
            self::Admission => "{$base}-ADM",
            self::Enrollment => $base,
            self::Receipt => 'REC',
        };
    }
}
