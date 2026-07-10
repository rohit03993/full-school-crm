<?php

namespace App\Support;

/**
 * Plain-language sidebar and page titles — single source for admin menu labels.
 */
final class CrmMenuLabels
{
    // —— Sidebar groups ——
    public const GROUP_LEADS = 'Leads';

    public const GROUP_CALLS = 'Calls';

    public const GROUP_WHATSAPP = 'WhatsApp';

    public const GROUP_STUDENTS = 'Students';

    public const GROUP_ACADEMICS = 'Academics';

    public const GROUP_REPORTS = 'Reports';

    public const GROUP_SETTINGS = 'Setup';

    public const GROUP_ADMIN = 'Admin';

    public const GROUP_WEBSITE = 'Website';

    // —— Top ——
    public static function dashboard(): string
    {
        return 'Dashboard';
    }

    public static function myMeetings(): string
    {
        return 'My work';
    }

    public static function myWork(): string
    {
        return self::myMeetings();
    }

    public static function myClasses(): string
    {
        return 'My classes';
    }

    // —— Leads ——
    public static function findStudent(): string
    {
        return 'Find student';
    }

    public static function leads(): string
    {
        return 'All leads';
    }

    public static function campusVisits(): string
    {
        return 'Campus visits';
    }

    public static function followUps(): string
    {
        return 'Follow-ups';
    }

    public static function myCallList(): string
    {
        return 'My call list';
    }

    public static function myCases(): string
    {
        return 'My cases';
    }

    public static function allCases(): string
    {
        return 'All cases';
    }

    // —— Calls ——
    public static function callQueue(): string
    {
        return 'Call queue';
    }

    public static function callReport(): string
    {
        return 'Call report';
    }

    // —— WhatsApp ——
    public static function whatsAppSetup(): string
    {
        return 'WhatsApp setup';
    }

    public static function whatsAppInbox(): string
    {
        return 'Inbox';
    }

    public static function whatsAppUsage(): string
    {
        return 'Usage & cost';
    }

    public static function whatsAppTemplates(): string
    {
        return 'Templates';
    }

    public static function whatsAppQuickCampaigns(): string
    {
        return 'Quick campaigns';
    }

    public static function whatsAppBulkCampaigns(): string
    {
        return 'Bulk campaigns';
    }

    public static function whatsAppMessageLog(): string
    {
        return 'Message history';
    }

    public static function whatsAppAutomations(): string
    {
        return 'Automations';
    }

    // —— Students ——
    public static function students(): string
    {
        return 'All students';
    }

    public static function admissions(): string
    {
        return 'Admissions';
    }

    public static function fees(): string
    {
        return 'Fees';
    }

    public static function importStudents(): string
    {
        return 'Import students';
    }

    public static function feeLedger(): string
    {
        return 'Fee ledger';
    }

    public static function bulkMiscCharges(): string
    {
        return 'Bulk extra charges';
    }

    // —— Academics ——
    public static function classes(): string
    {
        return 'Classes';
    }

    public static function addClassSection(): string
    {
        return 'Add class';
    }

    public static function schoolYears(): string
    {
        return 'School years';
    }

    public static function createExam(): string
    {
        return 'Create exam';
    }

    public static function examResults(): string
    {
        return 'Exam results';
    }

    public static function uploadMarksExcel(): string
    {
        return 'Upload marks (Excel)';
    }

    public static function attendance(): string
    {
        return 'Attendance';
    }

    public static function homework(): string
    {
        return 'Homework';
    }

    public static function examTypes(): string
    {
        return 'Exam types';
    }

    // —— Reports ——
    public static function reports(): string
    {
        return 'Reports';
    }

    // —— Setup ——
    public static function instituteSetup(): string
    {
        return 'School setup';
    }

    public static function instituteSettings(): string
    {
        return 'School profile';
    }

    public static function setupGuide(): string
    {
        return 'Setup guide';
    }

    public static function backups(): string
    {
        return 'Backups';
    }

    public static function feeSettings(): string
    {
        return 'Fee rules';
    }

    public static function customFields(): string
    {
        return 'Custom fields';
    }

    public static function terminology(): string
    {
        return 'Wording & labels';
    }

    public static function meetingTypes(): string
    {
        return 'Meeting types';
    }

    public static function biometricSetup(): string
    {
        return 'Biometric setup';
    }

    public static function websiteContent(): string
    {
        return 'Website content';
    }

    // —— Admin ——
    public static function staff(): string
    {
        return 'Staff';
    }

    public static function auditLog(): string
    {
        return 'Audit log';
    }

    public static function license(): string
    {
        return 'License';
    }

    public static function myAccount(): string
    {
        return 'My account';
    }

    /** Breadcrumb-style path for docs and hints. */
    public static function whatsAppPath(string $item): string
    {
        return self::GROUP_WHATSAPP.' → '.$item;
    }
}
