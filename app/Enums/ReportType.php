<?php

namespace App\Enums;

enum ReportType: string
{
    case Enquiries = 'enquiries';
    case LeadAging = 'lead_aging';
    case EnquirySources = 'enquiry_sources';
    case AdmissionsByCourse = 'admissions_by_course';
    case AdmissionsByStaff = 'admissions_by_staff';
    case AttendanceByBatch = 'attendance_by_batch';
    case AttendanceByStudent = 'attendance_by_student';
    case DailyAbsentSheet = 'daily_absent_sheet';
    case MonthlyStudentAttendance = 'monthly_student_attendance';
    case LowAttendanceAlert = 'low_attendance_alert';
    case Activities = 'activities';
    case TestMarks = 'test_marks';
    case FeeCollection = 'fee_collection';
    case PendingFees = 'pending_fees';
    case OverdueInstallments = 'overdue_installments';
    case Discounts = 'discounts';
    case PaymentModes = 'payment_modes';
    case AuditLogs = 'audit_logs';
    case FinancialSummary = 'financial_summary';
    case HomeworkCheckSummary = 'homework_check_summary';

    public function label(): string
    {
        return match ($this) {
            self::Enquiries => 'Enquiries (date range)',
            self::LeadAging => 'Open leads — aging',
            self::EnquirySources => 'Enquiry source-wise',
            self::AdmissionsByCourse => 'Admissions by course',
            self::AdmissionsByStaff => 'Admissions by staff',
            self::AttendanceByBatch => 'Attendance by batch',
            self::AttendanceByStudent => 'Attendance by student',
            self::DailyAbsentSheet => 'Daily absent sheet',
            self::MonthlyStudentAttendance => 'Monthly student attendance',
            self::LowAttendanceAlert => 'Low attendance alert',
            self::Activities => 'Tests & exams (marks)',
            self::TestMarks => 'Test marks (detail export)',
            self::FeeCollection => 'Fee collection',
            self::PendingFees => 'Pending fees',
            self::OverdueInstallments => 'Overdue installments',
            self::Discounts => 'Discount report',
            self::PaymentModes => 'Payment mode report',
            self::AuditLogs => 'Audit log report',
            self::FinancialSummary => 'Financial summary',
            self::HomeworkCheckSummary => 'Homework check (Done %)',
        };
    }

    public function isFinancial(): bool
    {
        return match ($this) {
            self::FeeCollection,
            self::PendingFees,
            self::OverdueInstallments,
            self::Discounts,
            self::PaymentModes,
            self::AuditLogs,
            self::FinancialSummary => true,
            default => false,
        };
    }

    public function requiredLicenseFeature(): ?LicenseFeature
    {
        return match ($this) {
            self::Enquiries, self::LeadAging, self::EnquirySources => LicenseFeature::Enquiries,
            self::AdmissionsByCourse, self::AdmissionsByStaff => LicenseFeature::Admissions,
            self::AttendanceByBatch,
            self::AttendanceByStudent,
            self::DailyAbsentSheet,
            self::MonthlyStudentAttendance,
            self::LowAttendanceAlert => LicenseFeature::Attendance,
            self::Activities, self::TestMarks => LicenseFeature::Marks,
            self::FeeCollection,
            self::PendingFees,
            self::OverdueInstallments,
            self::Discounts,
            self::PaymentModes,
            self::FinancialSummary => LicenseFeature::Fees,
            self::HomeworkCheckSummary => LicenseFeature::Homework,
            self::AuditLogs => null,
        };
    }

    public function staffCanExport(): bool
    {
        return ! $this->isFinancial();
    }

    public function usesDateRange(): bool
    {
        return match ($this) {
            self::PendingFees,
            self::OverdueInstallments,
            self::LeadAging => false,
            default => true,
        };
    }

    /**
     * Which filter fields apply to this report (hides irrelevant UI on Reports page).
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return match ($this) {
            self::LeadAging => [
                'course_id',
                'lead_source',
                'user_id',
                'min_days_open',
                'min_days_since_contact',
            ],
            self::Enquiries => ['date_from', 'date_to'],
            self::EnquirySources => ['date_from', 'date_to', 'lead_source'],
            self::AdmissionsByCourse => ['date_from', 'date_to', 'course_id'],
            self::AdmissionsByStaff => ['date_from', 'date_to', 'user_id'],
            self::AttendanceByBatch => ['date_from', 'date_to', 'batch_id'],
            self::AttendanceByStudent => ['date_from', 'date_to', 'student_id'],
            self::DailyAbsentSheet => ['date_from', 'date_to', 'batch_id'],
            self::MonthlyStudentAttendance => ['date_from', 'date_to', 'batch_id', 'student_id'],
            self::LowAttendanceAlert => ['date_from', 'date_to', 'batch_id', 'max_percentage'],
            self::Activities => ['date_from', 'date_to', 'activity_type_id'],
            self::TestMarks => ['date_from', 'date_to', 'batch_id', 'activity_type_id'],
            self::FeeCollection,
            self::Discounts,
            self::PaymentModes,
            self::FinancialSummary => ['date_from', 'date_to'],
            self::PendingFees,
            self::OverdueInstallments => ['course_id', 'batch_id'],
            self::AuditLogs => ['date_from', 'date_to', 'user_id'],
            self::HomeworkCheckSummary => ['date_from', 'date_to', 'batch_id'],
        };
    }

    public function showsFilter(string $key): bool
    {
        return in_array($key, $this->filterKeys(), true);
    }

    public function requiresFilter(string $key): bool
    {
        return in_array($key, $this->requiredFilterKeys(), true);
    }

    /**
     * Filters that must be set before the report can run.
     *
     * @return list<string>
     */
    public function requiredFilterKeys(): array
    {
        return match ($this) {
            self::AttendanceByStudent => ['student_id'],
            default => [],
        };
    }

    public function filterHint(): ?string
    {
        return match ($this) {
            self::LeadAging => 'All open leads in the pipeline (not enrolled, not Joined), newest contact gaps first. Optional filters below narrow the list — leave them blank to include everyone.',
            self::Enquiries => 'All enquiries created in the date range (including converted leads). Adjust dates below to change the period.',
            self::AttendanceByStudent => 'Pick a student, set the date range, then tap Apply. Shows daily batch attendance for that student.',
            self::PendingFees, self::OverdueInstallments => 'All pending or overdue records. Optionally narrow by course or batch.',
            self::LowAttendanceAlert => 'Students below the attendance threshold in the date range. Change the % only if you need a stricter cutoff.',
            default => 'Results load automatically. Leave optional filters blank to include all matching records.',
        };
    }
}
