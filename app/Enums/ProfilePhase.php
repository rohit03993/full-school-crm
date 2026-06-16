<?php

namespace App\Enums;

enum ProfilePhase: string
{
    case Lead = 'lead';
    case Admission = 'admission';
    case Enrolled = 'enrolled';
    case ActiveStudent = 'active_student';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Admission => 'Admission in progress',
            self::Enrolled => 'Enrolled',
            self::ActiveStudent => 'Active student',
        };
    }

    public function isLeadStage(): bool
    {
        return $this === self::Lead || $this === self::Admission;
    }
}
