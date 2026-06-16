<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Enquiry = 'enquiry';
    case AdmissionSubmitted = 'admission_submitted';
    case VerificationPending = 'verification_pending';
    case Approved = 'approved';
    case Enrolled = 'enrolled';
    case Completed = 'completed';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::AdmissionSubmitted => 'Admission Submitted',
            self::VerificationPending => 'Verification Pending',
            self::Approved => 'Approved',
            self::Enrolled => 'Enrolled',
            self::Completed => 'Completed',
            self::Dropped => 'Dropped',
        };
    }
}
