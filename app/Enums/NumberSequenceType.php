<?php

namespace App\Enums;

use App\Support\InstituteSettings;

enum NumberSequenceType: string
{
    case Enquiry = 'enquiry';
    case Admission = 'admission';
    case Enrollment = 'enrollment';
    case Receipt = 'receipt';

    public function prefix(): string
    {
        $base = InstituteSettings::numberPrefix();

        return match ($this) {
            self::Enquiry => "{$base}-ENQ",
            self::Admission => "{$base}-ADM",
            self::Enrollment => $base,
            self::Receipt => 'REC',
        };
    }
}
