<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AttendanceHubPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $title = 'Attendance';

    protected static ?string $slug = 'attendance-hub';

    protected static ?int $navigationSort = 39;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::attendance();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return false;
        }

        return AttendancePage::canAccess() || StaffAttendancePage::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Mark student and staff attendance from one place.';
    }

    public function content(Schema $schema): Schema
    {
        $cards = [];

        if (AttendancePage::canAccess()) {
            $cards[] = [
                'title' => 'Live punches',
                'description' => 'Biometric and live IN/OUT for today\'s classes.',
                'url' => AttendancePage::getUrl(['mode' => 'live']),
                'badge' => 'Start here',
                'tone' => 'primary',
            ];
            $cards[] = [
                'title' => 'Manual batch',
                'description' => 'Tap IN/OUT for a class section when the device is not used.',
                'url' => AttendancePage::getUrl(['mode' => 'manual']),
            ];
        }

        if (StaffAttendancePage::canAccess()) {
            $cards[] = [
                'title' => 'Staff attendance',
                'description' => 'Record or review staff day attendance.',
                'url' => StaffAttendancePage::getUrl(),
            ];
        }

        return $schema->components([
            View::make('filament.pages.partials.crm-hub')
                ->viewData([
                    'heading' => 'Attendance desk',
                    'intro' => 'Choose live punches, manual class marking, or staff attendance.',
                    'cards' => $cards,
                    'footer' => '<strong class="text-gray-900 dark:text-white">Tip:</strong> Parent WhatsApp on punch still follows Automations under WhatsApp.',
                ]),
        ]);
    }
}
