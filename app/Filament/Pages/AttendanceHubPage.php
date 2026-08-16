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
        return 'Mark students and staff from one place. Same machine and Face app — Roll No. for students, Staff ID for staff.';
    }

    public function content(Schema $schema): Schema
    {
        $cards = [];

        if (AttendancePage::canAccess()) {
            $cards[] = [
                'title' => 'Students — live punches',
                'description' => 'Biometric / Face IN–OUT for today\'s classes. Student Roll No. = device PIN.',
                'url' => AttendancePage::getUrl(['mode' => 'live']),
                'badge' => 'Students',
                'tone' => 'primary',
            ];
            $cards[] = [
                'title' => 'Students — manual batch',
                'description' => 'Mark a class section by hand when the machine is not used.',
                'url' => AttendancePage::getUrl(['mode' => 'manual']),
                'badge' => 'Students',
            ];
        }

        if (StaffAttendancePage::canAccess()) {
            $cards[] = [
                'title' => 'Staff attendance',
                'description' => 'IN/OUT for teachers and office staff. Staff ID = device PIN = Face ID.',
                'url' => StaffAttendancePage::getUrl(),
                'badge' => 'Staff',
            ];
        }

        return $schema->components([
            View::make('filament.pages.partials.crm-hub')
                ->viewData([
                    'heading' => 'Attendance desk',
                    'intro' => 'Students and staff share the same ADMS machine and Face app — the PIN decides who is who.',
                    'cards' => $cards,
                    'footer' => '<strong class="text-gray-900 dark:text-white">Setup:</strong> Student Roll No. and Staff ID must be unique. Put the same code on the ADMS machine and Face app. Missing Staff IDs → Admin → Staff → Assign missing Staff IDs, then Sync to Face API. Parent WhatsApp on punch still follows WhatsApp → Automations.',
                ]),
        ]);
    }
}
