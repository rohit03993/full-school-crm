<?php

namespace App\Enums;

enum LicenseFeature: string
{
    case Attendance = 'attendance';
    case Marks = 'marks';
    case Fees = 'fees';
    case Enquiries = 'enquiries';
    case Calls = 'calls';
    case Admissions = 'admissions';
    case WhatsApp = 'whatsapp';
    case Portal = 'portal';
    case Reports = 'reports';
    case Results = 'results';
    case Marksheets = 'marksheets';
    case Homework = 'homework';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Attendance',
            self::Marks => 'Marks & activities',
            self::Fees => 'Fees & collections',
            self::Enquiries => 'Leads & enquiries',
            self::Calls => 'Calling CRM',
            self::Admissions => 'Admissions',
            self::WhatsApp => 'WhatsApp messaging',
            self::Portal => 'Student portal',
            self::Reports => 'Reports',
            self::Results => 'Result declaration',
            self::Marksheets => 'PDF marksheets',
            self::Homework => 'Homework',
            self::Website => 'Public website',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Attendance => 'Batch and session attendance.',
            self::Marks => 'Activity marks, imports, and academics.',
            self::Fees => 'Fee dashboard, installments, and collections.',
            self::Enquiries => 'Enquiry pipeline, follow-ups, and campus visits.',
            self::Calls => 'Call queue, tracking, and call reports.',
            self::Admissions => 'Admission records and enrolment workflow.',
            self::WhatsApp => 'Templates and bulk WhatsApp campaigns.',
            self::Portal => 'Student/parent login portal.',
            self::Reports => 'Operational and academic reports.',
            self::Results => 'Publish exam results to students.',
            self::Marksheets => 'Issue downloadable PDF marksheets.',
            self::Homework => 'Homework assignments and sharing.',
            self::Website => 'Public site content and branding.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $feature): array => [$feature->value => $feature->label()])
            ->all();
    }
}
