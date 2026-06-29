<?php

namespace App\Enums;

enum CrmPermission: string
{
    case DashboardOwnerStats = 'crm.dashboard.owner';
    case DashboardCallingStats = 'crm.dashboard.calling';
    case DashboardFinanceStats = 'crm.dashboard.finance';

    case LeadsViewAll = 'crm.leads.view_all';
    case LeadsViewAssigned = 'crm.leads.view_assigned';
    case LeadsCall = 'crm.leads.call';
    case LeadsReassign = 'crm.leads.reassign';
    case VisitsViewAll = 'crm.visits.view_all';

    case StudentsView = 'crm.students.view';
    case StudentsEdit = 'crm.students.edit';
    case StudentsImport = 'crm.students.import';

    case AdmissionsView = 'crm.admissions.view';
    case AdmissionsApprove = 'crm.admissions.approve';

    case FeesCollect = 'crm.fees.collect';
    case FeesAdjustStructure = 'crm.fees.adjust_structure';
    case FeesWaivePenalty = 'crm.fees.waive_penalty';

    case AttendanceMark = 'crm.attendance.mark';
    case AttendanceWorkshops = 'crm.attendance.workshops';
    case MarksImport = 'crm.marks.import';
    case HomeworkManage = 'crm.homework.manage';
    case AcademicsManage = 'crm.academics.manage';

    case WhatsappCampaigns = 'crm.whatsapp.campaigns';
    case WhatsappSettings = 'crm.whatsapp.settings';

    case ReportsView = 'crm.reports.view';
    case ReportsExport = 'crm.reports.export';

    case StaffManage = 'crm.staff.manage';
    case SettingsManage = 'crm.settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::DashboardOwnerStats => 'Owner dashboard stats',
            self::DashboardCallingStats => 'Calling dashboard stats',
            self::DashboardFinanceStats => 'Finance dashboard stats',
            self::LeadsViewAll => 'View all enquiries',
            self::LeadsViewAssigned => 'View assigned leads',
            self::LeadsCall => 'Call queue & log calls',
            self::LeadsReassign => 'Reassign leads',
            self::VisitsViewAll => 'View all campus visits',
            self::StudentsView => 'View students',
            self::StudentsEdit => 'Edit student details',
            self::StudentsImport => 'Import students',
            self::AdmissionsView => 'View admissions',
            self::AdmissionsApprove => 'Approve admissions',
            self::FeesCollect => 'Collect fees',
            self::FeesAdjustStructure => 'Adjust fee structure',
            self::FeesWaivePenalty => 'Waive late fees',
            self::AttendanceMark => 'Mark batch attendance',
            self::AttendanceWorkshops => 'Workshop / event attendance',
            self::MarksImport => 'Import marks',
            self::HomeworkManage => 'Assign homework & view tracking',
            self::AcademicsManage => 'Manage courses, batches, exam types',
            self::WhatsappCampaigns => 'WhatsApp campaigns',
            self::WhatsappSettings => 'WhatsApp settings',
            self::ReportsView => 'View reports',
            self::ReportsExport => 'Export reports',
            self::StaffManage => 'Manage staff accounts',
            self::SettingsManage => 'Institute & system settings',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
