<?php

namespace App\Enums;

enum DocumentType: string
{
    case Photo = 'photo';
    case Aadhaar = 'aadhaar';
    case Marksheet = 'marksheet';
    case Signature = 'signature';
    case TransferCertificate = 'tc';
    case CharacterCertificate = 'character';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Student Photo',
            self::Aadhaar => 'Aadhaar Card',
            self::Marksheet => 'Marksheet',
            self::Signature => 'Signature',
            self::TransferCertificate => 'Transfer Certificate',
            self::CharacterCertificate => 'Character Certificate',
            self::Other => 'Other Document',
        };
    }

    public function isRequiredForAdmission(): bool
    {
        return in_array($this, [
            self::Photo,
            self::Aadhaar,
            self::Marksheet,
            self::Signature,
        ], true);
    }
}
