<?php

namespace App\Enums;

enum ReportType: string
{
    case Enquiries = 'enquiries';
    case EnquirySources = 'enquiry_sources';
    case AdmissionsByCourse = 'admissions_by_course';
    case AdmissionsByStaff = 'admissions_by_staff';
    case AttendanceByBatch = 'attendance_by_batch';
    case AttendanceByStudent = 'attendance_by_student';
    case Activities = 'activities';
    case TestMarks = 'test_marks';
    case FeeCollection = 'fee_collection';
    case PendingFees = 'pending_fees';
    case OverdueInstallments = 'overdue_installments';
    case Discounts = 'discounts';
    case PaymentModes = 'payment_modes';
    case AuditLogs = 'audit_logs';
    case FinancialSummary = 'financial_summary';

    public function label(): string
    {
        return match ($this) {
            self::Enquiries => 'Enquiries (date range)',
            self::EnquirySources => 'Enquiry source-wise',
            self::AdmissionsByCourse => 'Admissions by course',
            self::AdmissionsByStaff => 'Admissions by staff',
            self::AttendanceByBatch => 'Attendance by batch',
            self::AttendanceByStudent => 'Attendance by student',
            self::Activities => 'Tests & exams (marks)',
            self::TestMarks => 'Test marks (detail export)',
            self::FeeCollection => 'Fee collection',
            self::PendingFees => 'Pending fees',
            self::OverdueInstallments => 'Overdue installments',
            self::Discounts => 'Discount report',
            self::PaymentModes => 'Payment mode report',
            self::AuditLogs => 'Audit log report',
            self::FinancialSummary => 'Financial summary',
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

    public function staffCanExport(): bool
    {
        return ! $this->isFinancial();
    }
}
