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
    case Cases = 'cases';
    case Certificates = 'certificates';
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
            self::Attendance => 'Attendance Management',
            self::Marks => 'Marks & Exams Management',
            self::Fees => 'Fees Management',
            self::Enquiries => 'Leads & Enquiries Management',
            self::Calls => 'Calling Management',
            self::Admissions => 'Admissions Management',
            self::Cases => 'Student Cases Management',
            self::Certificates => 'Certificates Management',
            self::WhatsApp => 'WhatsApp Management',
            self::Portal => 'Student Portal',
            self::Reports => 'Reports Management',
            self::Results => 'Results Declaration',
            self::Marksheets => 'Marksheets Management',
            self::Homework => 'Homework Management',
            self::Website => 'Website CMS',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Attendance => 'Batch and session attendance, biometric and face punch.',
            self::Marks => 'Activity marks, exam windows, and academics.',
            self::Fees => 'Fee dashboard, installments, and collections.',
            self::Enquiries => 'Enquiry pipeline, campus visits, and lead follow-ups.',
            self::Calls => 'Call queue, dual-mode call logging, and call reports.',
            self::Admissions => 'Admission records and enrolment workflow.',
            self::Cases => 'Enrolled-student support cases: open, transfer, close.',
            self::Certificates => 'Issue TC, bonafide, character, and fee certificates.',
            self::WhatsApp => 'Templates, inbox, and bulk WhatsApp campaigns.',
            self::Portal => 'Student/parent login portal.',
            self::Reports => 'Operational and academic reports.',
            self::Results => 'Publish exam results to students.',
            self::Marksheets => 'Issue downloadable PDF marksheets.',
            self::Homework => 'Homework assignments and checking.',
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
