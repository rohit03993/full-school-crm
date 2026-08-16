<?php

namespace App\Support;

use App\Enums\CrmPermission;
use App\Enums\StaffJobRole;

class StaffRolePermissions
{
    /**
     * Permissions granted by each job role. Multiple roles on one user = union of all lists.
     *
     * Super Admin vault (never granted here): StaffManage, SettingsManage, CasesViewAll,
     * WhatsappSettings, MetaWhatsappSettings, waive-approval UI (role check), audit/backups/setup.
     *
     * @return array<string, list<CrmPermission>>
     */
    public static function matrix(): array
    {
        return [
            StaffJobRole::Counsellor->value => [
                CrmPermission::DashboardCallingStats,
                CrmPermission::LeadsViewAssigned,
                CrmPermission::LeadsCall,
                CrmPermission::StudentsView,
                CrmPermission::CasesView,
                CrmPermission::CasesOpen,
                CrmPermission::CasesAssign,
                CrmPermission::CasesClose,
            ],
            StaffJobRole::AdmissionOfficer->value => [
                CrmPermission::DashboardCallingStats,
                CrmPermission::LeadsViewAll,
                CrmPermission::LeadsViewAssigned,
                CrmPermission::LeadsCall,
                CrmPermission::LeadsReassign,
                CrmPermission::VisitsViewAll,
                CrmPermission::StudentsView,
                CrmPermission::StudentsEdit,
                CrmPermission::StudentsImport,
                CrmPermission::CertificatesView,
                CrmPermission::CertificatesIssue,
                CrmPermission::AdmissionsView,
                CrmPermission::AdmissionsApprove,
                CrmPermission::CasesView,
                CrmPermission::CasesOpen,
                CrmPermission::CasesAssign,
                CrmPermission::CasesClose,
            ],
            StaffJobRole::Accountant->value => [
                CrmPermission::DashboardFinanceStats,
                CrmPermission::StudentsView,
                CrmPermission::CertificatesView,
                CrmPermission::CertificatesIssue,
                CrmPermission::AdmissionsView,
                CrmPermission::FeesCollect,
                CrmPermission::ReportsView,
                CrmPermission::ReportsExport,
                CrmPermission::CasesView,
                CrmPermission::CasesAssign,
                CrmPermission::CasesClose,
            ],
            StaffJobRole::FeeAdjuster->value => [
                CrmPermission::DashboardFinanceStats,
                CrmPermission::StudentsView,
                CrmPermission::AdmissionsView,
                CrmPermission::FeesAdjustStructure,
                CrmPermission::FeesWaivePenalty,
                CrmPermission::ReportsView,
                CrmPermission::ReportsExport,
                CrmPermission::CasesView,
                CrmPermission::CasesOpen,
                CrmPermission::CasesAssign,
                CrmPermission::CasesClose,
            ],
            StaffJobRole::AcademicCoordinator->value => [
                CrmPermission::StudentsView,
                CrmPermission::CertificatesView,
                CrmPermission::CertificatesIssue,
                CrmPermission::AttendanceMark,
                CrmPermission::AttendanceWorkshops,
                CrmPermission::MarksImport,
                CrmPermission::MarksPublish,
                CrmPermission::HomeworkManage,
                CrmPermission::AcademicsManage,
                CrmPermission::CasesView,
                CrmPermission::CasesOpen,
                CrmPermission::CasesAssign,
                CrmPermission::CasesClose,
            ],
            StaffJobRole::Teacher->value => [
                CrmPermission::StudentsView,
                CrmPermission::AttendanceMark,
                CrmPermission::MarksImport,
                CrmPermission::CasesView,
                CrmPermission::CasesOpen,
                CrmPermission::CasesAssign,
                CrmPermission::CasesClose,
            ],
            StaffJobRole::MessagingCoordinator->value => [
                CrmPermission::StudentsView,
                CrmPermission::WhatsappCampaigns,
                CrmPermission::WhatsappOps,
                CrmPermission::CasesView,
                CrmPermission::CasesOpen,
                CrmPermission::CasesAssign,
                CrmPermission::CasesClose,
            ],
        ];
    }

    /**
     * Full operational access for legacy generic "staff" logins until roles are assigned.
     *
     * @return list<CrmPermission>
     */
    public static function legacyStaffPermissions(): array
    {
        return array_values(array_filter(
            CrmPermission::cases(),
            fn (CrmPermission $permission): bool => ! in_array($permission, [
                CrmPermission::DashboardOwnerStats,
                CrmPermission::DashboardFinanceStats,
                CrmPermission::CasesViewAll,
                CrmPermission::WhatsappSettings,
                CrmPermission::MetaWhatsappSettings,
                CrmPermission::WhatsappCampaigns,
                CrmPermission::WhatsappOps,
                CrmPermission::StaffManage,
                CrmPermission::SettingsManage,
                CrmPermission::AcademicsManage,
                CrmPermission::AttendanceMark,
                CrmPermission::AttendanceWorkshops,
                CrmPermission::MarksImport,
                CrmPermission::MarksPublish,
                CrmPermission::FeesCollect,
                CrmPermission::FeesAdjustStructure,
                CrmPermission::FeesWaivePenalty,
                CrmPermission::LeadsReassign,
                CrmPermission::ReportsExport,
            ], true),
        ));
    }

    /**
     * @return list<string>
     */
    public static function permissionNamesForRole(StaffJobRole $role): array
    {
        return array_map(
            fn (CrmPermission $permission): string => $permission->value,
            self::matrix()[$role->value] ?? [],
        );
    }
}
