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
        return match ($this) {
            self::Enquiry => 'FI-ENQ',
            self::Admission => 'FI-ADM',
            self::Enrollment => 'FI',
            self::Receipt => 'REC',
        };
    }
}
