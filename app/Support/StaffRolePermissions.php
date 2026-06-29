<?php

namespace App\Support;

use App\Enums\CrmPermission;
use App\Enums\StaffJobRole;

class StaffRolePermissions
{
    /**
     * Permissions granted by each job role. Multiple roles on one user = union of all lists.
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
                CrmPermission::AdmissionsView,
                CrmPermission::AdmissionsApprove,
            ],
            StaffJobRole::Accountant->value => [
                CrmPermission::DashboardFinanceStats,
                CrmPermission::StudentsView,
                CrmPermission::AdmissionsView,
                CrmPermission::FeesCollect,
                CrmPermission::ReportsView,
            ],
            StaffJobRole::AcademicCoordinator->value => [
                CrmPermission::StudentsView,
                CrmPermission::AttendanceMark,
                CrmPermission::AttendanceWorkshops,
                CrmPermission::MarksImport,
                CrmPermission::HomeworkManage,
            ],
            StaffJobRole::MessagingCoordinator->value => [
                CrmPermission::StudentsView,
                CrmPermission::WhatsappCampaigns,
                CrmPermission::HomeworkManage,
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
                CrmPermission::WhatsappSettings,
                CrmPermission::WhatsappCampaigns,
                CrmPermission::StaffManage,
                CrmPermission::SettingsManage,
                CrmPermission::AcademicsManage,
                CrmPermission::AttendanceMark,
                CrmPermission::AttendanceWorkshops,
                CrmPermission::MarksImport,
                CrmPermission::FeesAdjustStructure,
                CrmPermission::FeesWaivePenalty,
                CrmPermission::LeadsReassign,
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
