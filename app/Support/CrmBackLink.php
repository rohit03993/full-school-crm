<?php

namespace App\Support;

use App\Filament\Pages\AccountingLedgerPage;
use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Pages\AddClassSectionPage;
use App\Filament\Pages\AllCasesPage;
use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\BackupsPage;
use App\Filament\Pages\BatchAttendancePage;
use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Filament\Pages\BulkMiscChargePage;
use App\Filament\Pages\BulkStaffImportPage;
use App\Filament\Pages\BulkStudentImportPage;
use App\Filament\Pages\ClassSectionsPage;
use App\Filament\Pages\ConsolidatedReportCardsPage;
use App\Filament\Pages\CreateExamWindowPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ExamWindowPage;
use App\Filament\Pages\ExamWindowsPage;
use App\Filament\Pages\FeesDashboardPage;
use App\Filament\Pages\FeesHubPage;
use App\Filament\Pages\FirstRunSetup;
use App\Filament\Pages\HomeworkCheckPage;
use App\Filament\Pages\HomeworkPage;
use App\Filament\Pages\HomeworkReviewPage;
use App\Filament\Pages\InstituteSetup;
use App\Filament\Pages\LicenseExpiredPage;
use App\Filament\Pages\ListMetaWhatsAppMessagesPage;
use App\Filament\Pages\LivePunchAttendancePage;
use App\Filament\Pages\ManageAttendanceBiometricPage;
use App\Filament\Pages\ManageAttendanceDisplayPage;
use App\Filament\Pages\ManageCustomFields;
use App\Filament\Pages\ManageFacePlatformPage;
use App\Filament\Pages\ManageFeeSettings;
use App\Filament\Pages\ManagePushNotificationsPage;
use App\Filament\Pages\ManageInstituteSettings;
use App\Filament\Pages\ManageMeetingForOptions;
use App\Filament\Pages\ManageMetaWhatsAppSettings;
use App\Filament\Pages\ManageTerminology;
use App\Filament\Pages\ManageWhatsAppSettings;
use App\Filament\Pages\MiscChargeAdjustmentRequestsPage;
use App\Filament\Pages\PaymentCancellationRequestsPage;
use App\Filament\Pages\MyCasesPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Pages\SessionAttendancePage;
use App\Filament\Pages\SetupGuide;
use App\Filament\Pages\SetupHubPage;
use App\Filament\Pages\StaffAttendancePage;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Pages\SubmitHomeworkPage;
use App\Filament\Pages\TestMarksReviewPage;
use App\Filament\Pages\WhatsAppAnalyticsPage;
use App\Filament\Pages\WhatsAppHubPage;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Filament\Resources\BiometricDevices\BiometricDeviceResource;
use App\Filament\Resources\FaceVerificationRequests\FaceVerificationRequestResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource;
use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Filament\Resources\WhatsAppLiveCampaigns\WhatsAppLiveCampaignResource;
use App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Throwable;

/**
 * Resolves the "go back" destination shown at the top of every admin page.
 *
 * Leaf screens point at the hub that owns them ({@see CrmNavigation}); resource
 * child pages point at their own list. The rendered control still prefers real
 * browser history when the visitor arrived from inside the CRM, so this URL is
 * the safety net for direct links, refreshes, and new tabs.
 */
class CrmBackLink
{
    /**
     * Pages that are a destination in themselves — no back control.
     *
     * @var list<class-string>
     */
    private const ROOT_PAGES = [
        Dashboard::class,
        FirstRunSetup::class,
        LicenseExpiredPage::class,
    ];

    /**
     * Leaf page => owning hub page (or resource list).
     *
     * @var array<class-string, class-string>
     */
    private const PAGE_PARENTS = [
        AttendancePage::class => AttendanceHubPage::class,
        StaffAttendancePage::class => AttendanceHubPage::class,
        LivePunchAttendancePage::class => AttendanceHubPage::class,
        BatchAttendancePage::class => AttendanceHubPage::class,

        FeesDashboardPage::class => FeesHubPage::class,
        BulkMiscChargePage::class => FeesHubPage::class,
        MiscChargeAdjustmentRequestsPage::class => FeesHubPage::class,
        PaymentCancellationRequestsPage::class => FeesHubPage::class,
        AccountingLedgerPage::class => FeesHubPage::class,

        WhatsAppInboxPage::class => WhatsAppHubPage::class,
        WhatsAppAnalyticsPage::class => WhatsAppHubPage::class,
        ListMetaWhatsAppMessagesPage::class => WhatsAppHubPage::class,
        ManageMetaWhatsAppSettings::class => WhatsAppHubPage::class,
        ManageWhatsAppSettings::class => WhatsAppHubPage::class,

        SetupGuide::class => SetupHubPage::class,
        InstituteSetup::class => SetupHubPage::class,
        ManageInstituteSettings::class => SetupHubPage::class,
        ManageTerminology::class => SetupHubPage::class,
        ManageCustomFields::class => SetupHubPage::class,
        ManageMeetingForOptions::class => SetupHubPage::class,
        ManageFeeSettings::class => SetupHubPage::class,
        ManagePushNotificationsPage::class => SetupHubPage::class,
        ManageAttendanceBiometricPage::class => SetupHubPage::class,
        ManageFacePlatformPage::class => SetupHubPage::class,
        ManageAttendanceDisplayPage::class => SetupHubPage::class,
        BackupsPage::class => SetupHubPage::class,

        SubmitHomeworkPage::class => HomeworkPage::class,
        HomeworkReviewPage::class => HomeworkPage::class,
        HomeworkCheckPage::class => HomeworkPage::class,

        AddClassSectionPage::class => ClassSectionsPage::class,

        ExamWindowPage::class => ExamWindowsPage::class,
        CreateExamWindowPage::class => ExamWindowsPage::class,
        TestMarksReviewPage::class => ExamWindowsPage::class,
        ConsolidatedReportCardsPage::class => ExamWindowsPage::class,
        BulkActivityMarksImportPage::class => ExamWindowsPage::class,

        ActivityAttendancePage::class => ActivitySessionResource::class,
        SessionAttendancePage::class => ActivitySessionResource::class,

        MyCasesPage::class => MyMeetingsPage::class,
        AllCasesPage::class => MyMeetingsPage::class,

        StudentProfilePage::class => StudentResource::class,
        BulkStudentImportPage::class => StudentResource::class,
        BulkStaffImportPage::class => StaffResource::class,
    ];

    /**
     * Resource list page => owning hub, for resources kept off the sidebar.
     *
     * @var array<class-string, class-string>
     */
    private const RESOURCE_PARENTS = [
        WhatsAppCampaignResource::class => WhatsAppHubPage::class,
        WhatsAppLiveCampaignResource::class => WhatsAppHubPage::class,
        MetaWhatsAppTemplateResource::class => WhatsAppHubPage::class,
        WhatsAppTemplateResource::class => WhatsAppHubPage::class,
        BiometricDeviceResource::class => SetupHubPage::class,
        FaceVerificationRequestResource::class => SetupHubPage::class,
        HomeworkAssignmentResource::class => HomeworkPage::class,
    ];

    /**
     * @param  array<int, string>  $scopes  Render hook scopes: page class, then resource class.
     * @return array{url: string, label: string}|null
     */
    public static function forScopes(array $scopes): ?array
    {
        $page = $scopes[0] ?? null;

        if (! is_string($page) || $page === '' || in_array($page, self::ROOT_PAGES, true)) {
            return null;
        }

        $target = self::resolveTarget($page, $scopes);

        if ($target === null) {
            $target = Dashboard::class;
        }

        $link = self::linkTo($target);

        return $link ?? self::linkTo(Dashboard::class);
    }

    /**
     * @param  array<int, string>  $scopes
     * @return class-string|null
     */
    private static function resolveTarget(string $page, array $scopes): ?string
    {
        if (isset(self::PAGE_PARENTS[$page])) {
            return self::PAGE_PARENTS[$page];
        }

        $resource = self::resourceScope($scopes);

        if ($resource === null) {
            return null;
        }

        // Create / edit / view pages belong to their own list; the list itself falls back to its hub.
        if (! is_subclass_of($page, ListRecords::class)) {
            return $resource;
        }

        return self::RESOURCE_PARENTS[$resource] ?? null;
    }

    /**
     * @param  array<int, string>  $scopes
     * @return class-string|null
     */
    private static function resourceScope(array $scopes): ?string
    {
        foreach (array_slice($scopes, 1) as $scope) {
            if (is_string($scope) && is_subclass_of($scope, Resource::class)) {
                return $scope;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $target
     * @return array{url: string, label: string}|null
     */
    private static function linkTo(string $target): ?array
    {
        try {
            if (! $target::canAccess()) {
                return null;
            }

            $url = is_subclass_of($target, Resource::class)
                ? $target::getUrl('index')
                : $target::getUrl();
        } catch (Throwable) {
            return null;
        }

        return [
            'url' => $url,
            'label' => self::labelFor($target),
        ];
    }

    /**
     * @param  class-string  $target
     */
    private static function labelFor(string $target): string
    {
        try {
            $label = (string) $target::getNavigationLabel();
        } catch (Throwable) {
            $label = '';
        }

        if ($label === '' && is_subclass_of($target, Resource::class)) {
            $label = (string) $target::getPluralModelLabel();
        }

        return $label === '' ? 'Dashboard' : $label;
    }
}
