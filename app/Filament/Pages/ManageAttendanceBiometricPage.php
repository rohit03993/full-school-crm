<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\Punch\AttendanceBiometricStatusService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ManageAttendanceBiometricPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?string $navigationLabel = 'Biometric Attendance';

    protected static ?string $title = 'Biometric Attendance Setup';

    protected static ?int $navigationSort = 58;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Connect EasyTimePro punch device data to Academics → Attendance.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.attendance-biometric-setup')
                ->viewData(fn (AttendanceBiometricStatusService $status): array => [
                    'status' => $status->status(),
                    'rollHint' => $status->rollMappingHint(),
                    'whatsappSettingsUrl' => ManageWhatsAppSettings::getUrl(),
                    'attendanceUrl' => AttendancePage::getUrl(),
                ]),
        ]);
    }
}
