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
    case FeeAdjuster = 'fee_adjuster';
    case AcademicCoordinator = 'academic_coordinator';
    case Teacher = 'teacher';
    case MessagingCoordinator = 'messaging_coordinator';

    public function label(): string
    {
        return match ($this) {
            self::Counsellor => 'Counsellor (calls & leads)',
            self::AdmissionOfficer => 'Admission officer',
            self::Accountant => 'Accountant (fees)',
            self::FeeAdjuster => 'Fee adjuster (discounts & structure)',
            self::AcademicCoordinator => 'Academic coordinator',
            self::Teacher => 'Teacher / Faculty',
            self::MessagingCoordinator => 'Messaging (WhatsApp)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Counsellor => 'Assigned to Call, call queue, log calls, follow-ups',
            self::AdmissionOfficer => 'Enquiries, admissions, student edits, imports',
            self::Accountant => 'Collect fees, receipts, fee reports (including download)',
            self::FeeAdjuster => 'Adjust fee plan, discounts, installments; request waive/discount (admin approves)',
            self::AcademicCoordinator => 'Courses, batches, exams, attendance, marks upload & publish, homework',
            self::Teacher => 'Own classes only: attendance, homework submit, marks entry (no publish or setup)',
            self::MessagingCoordinator => 'WhatsApp campaigns, inbox, templates & live sends (not API settings)',
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
