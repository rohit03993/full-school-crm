<?php

namespace App\Enums;

enum WhatsAppInchatAudience: string
{
    case VisitingLead = 'visiting_lead';
    case ExistingStudent = 'existing_student';
    case Anyone = 'anyone';

    public function label(): string
    {
        return match ($this) {
            self::VisitingLead => 'Visiting lead',
            self::ExistingStudent => 'Existing student',
            self::Anyone => 'Anyone',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
