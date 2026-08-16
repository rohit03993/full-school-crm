<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Filament\Resources\BiometricDevices\BiometricDeviceResource;
use App\Filament\Resources\FaceVerificationRequests\FaceVerificationRequestResource;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SetupHubPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Setup';

    protected static ?string $slug = 'setup-hub';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function getNavigationLabel(): string
    {
        return 'Setup';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Institute configuration — branding, terminology, devices, and backups.';
    }

    public function content(Schema $schema): Schema
    {
        $cards = [];

        if (SetupGuide::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::setupGuide(),
                'description' => 'Checklists by school, coaching, or college type.',
                'url' => SetupGuide::getUrl(),
                'badge' => 'Start here',
                'tone' => 'primary',
            ];
        }

        if (InstituteSetup::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::instituteSetup(),
                'description' => 'Onboarding status and next recommended setup steps.',
                'url' => InstituteSetup::getUrl(),
            ];
        }

        if (ManageInstituteSettings::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::instituteSettings(),
                'description' => 'Name, logo, address, and PDF header/footer.',
                'url' => ManageInstituteSettings::getUrl(),
            ];
        }

        if (ManageTerminology::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::terminology(),
                'description' => 'Rename Batch, Course, and other labels for your institute.',
                'url' => ManageTerminology::getUrl(),
            ];
        }

        if (ManageCustomFields::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::customFields(),
                'description' => 'Extra fields on students and enquiries.',
                'url' => ManageCustomFields::getUrl(),
            ];
        }

        if (ManageMeetingForOptions::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::meetingTypes(),
                'description' => 'Meeting-for choices on Find student and enquiry forms.',
                'url' => ManageMeetingForOptions::getUrl(),
            ];
        }

        if (ManageFeeSettings::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::feeSettings(),
                'description' => 'GST, late fee, and fee collection rules.',
                'url' => ManageFeeSettings::getUrl(),
            ];
        }

        if (ManagePushNotificationsPage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::pushNotifications(),
                'description' => 'PWA lock-screen alerts for staff and parents.',
                'url' => ManagePushNotificationsPage::getUrl(),
            ];
        }

        if (ManageAttendanceBiometricPage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::biometricSetup(),
                'description' => 'EasyTimePro / punch device mapping.',
                'url' => ManageAttendanceBiometricPage::getUrl(),
            ];
        }

        if (BiometricDeviceResource::canAccess()) {
            $cards[] = [
                'title' => 'Biometric devices',
                'description' => 'Register devices that write punches into the CRM.',
                'url' => BiometricDeviceResource::getUrl('index'),
            ];
        }

        if (ManageFacePlatformPage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::facePlatform(),
                'description' => 'Face verification platform settings.',
                'url' => ManageFacePlatformPage::getUrl(),
            ];
        }

        if (FaceVerificationRequestResource::canAccess()) {
            $cards[] = [
                'title' => 'Face verification requests',
                'description' => 'Review face-match verification queue.',
                'url' => FaceVerificationRequestResource::getUrl('index'),
            ];
        }

        if (ManageAttendanceDisplayPage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::attendanceDisplay(),
                'description' => 'Reception TV / live punch display.',
                'url' => ManageAttendanceDisplayPage::getUrl(),
            ];
        }

        if (BackupsPage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::backups(),
                'description' => 'Database backups and Google Drive.',
                'url' => BackupsPage::getUrl(),
            ];
        }

        return $schema->components([
            View::make('filament.pages.partials.crm-hub')
                ->viewData([
                    'heading' => 'Setup desk',
                    'intro' => 'Configure the institute once. Day-to-day work stays under Leads, Students, and Academics.',
                    'cards' => $cards,
                    'footer' => '<strong class="text-gray-900 dark:text-white">Note:</strong> My account stays in the top-right user menu — not here.',
                ]),
        ]);
    }
}
