<?php

namespace App\Enums;

enum LicensePlan: string
{
    case Starter = 'starter';
    case AcademicPlus = 'academic_plus';
    case FullCrm = 'full_crm';
    case FullResults = 'full_results';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::AcademicPlus => 'Academic+',
            self::FullCrm => 'Full CRM',
            self::FullResults => 'Full CRM + Results',
            self::Custom => 'Custom (manual toggles)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Starter => 'Attendance and marks — core academics only.',
            self::AcademicPlus => 'Starter plus fees and homework.',
            self::FullCrm => 'Leads, calls, admissions, portal, WhatsApp, reports, and website.',
            self::FullResults => 'Everything in Full CRM plus result publish and PDF marksheets.',
            self::Custom => 'Pick individual modules below.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $plan): array => [$plan->value => $plan->label()])
            ->all();
    }
}
