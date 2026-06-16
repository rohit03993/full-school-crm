<?php

namespace App\Enums;

enum AdmissionStatus: string
{
    case Submitted = 'submitted';
    case VerificationPending = 'verification_pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Admission Submitted',
            self::VerificationPending => 'Verification Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
