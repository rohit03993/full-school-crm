<?php

namespace App\Enums;

/**
 * Assignable job roles for staff — a user may have any combination (1, 2, 3, or all).
 * Permissions from every assigned role are combined (union).
 */
enum StaffJobRole: string
{
    case Counsellor = 'counsellor';
    case AdmissionOfficer = 'admission_officer';
    case Accountant = 'accountant';
    case AcademicCoordinator = 'academic_coordinator';
    case MessagingCoordinator = 'messaging_coordinator';

    public function label(): string
    {
        return match ($this) {
            self::Counsellor => 'Counsellor (calls & leads)',
            self::AdmissionOfficer => 'Admission officer',
            self::Accountant => 'Accountant (fees)',
            self::AcademicCoordinator => 'Academic coordinator',
            self::MessagingCoordinator => 'Messaging (WhatsApp)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Counsellor => 'Assigned to Call, call queue, log calls, follow-ups',
            self::AdmissionOfficer => 'Enquiries, admissions, student edits, imports',
            self::Accountant => 'Collect fees, receipts, fee reports',
            self::AcademicCoordinator => 'Batch attendance, workshops, tests & marks upload',
            self::MessagingCoordinator => 'WhatsApp campaigns and bulk messaging',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
