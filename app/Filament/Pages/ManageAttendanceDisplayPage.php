<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\Punch\AttendanceDisplaySettingsService;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ManageAttendanceDisplayPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTv;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::attendanceDisplay();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::attendanceDisplay();
    }

    protected static ?int $navigationSort = 59;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public bool $enabled = false;

    public ?string $displayUrl = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function mount(AttendanceDisplaySettingsService $settings): void
    {
        $this->enabled = $settings->isEnabled();
        $this->displayUrl = $settings->displayUrl();
    }

    public function getSubheading(): ?string
    {
        return 'Full-screen reception TV — shows student photo and punch details when attendance is marked (device or manual IN/OUT).';
    }

    public function enableDisplay(AttendanceDisplaySettingsService $settings): void
    {
        if ($settings->token() === null) {
            $settings->regenerateToken();
        }

        $settings->enable(true);
        $this->syncFromSettings($settings);

        Notification::make()
            ->title('Attendance display enabled')
            ->body('Open the display URL on a reception TV or tablet in full-screen mode.')
            ->success()
            ->send();
    }

    public function disableDisplay(AttendanceDisplaySettingsService $settings): void
    {
        $settings->enable(false);
        $this->syncFromSettings($settings);

        Notification::make()
            ->title('Attendance display disabled')
            ->success()
            ->send();
    }

    public function regenerateDisplayToken(AttendanceDisplaySettingsService $settings): void
    {
        $settings->regenerateToken();

        if (! $settings->isEnabled()) {
            $settings->enable(true);
        }

        $this->syncFromSettings($settings);

        Notification::make()
            ->title('Display link regenerated')
            ->body('Update the URL on your reception screen — the old link no longer works.')
            ->warning()
            ->send();
    }

    private function syncFromSettings(AttendanceDisplaySettingsService $settings): void
    {
        $this->enabled = $settings->isEnabled();
        $this->displayUrl = $settings->displayUrl();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.attendance-display-setup')
                ->viewData(fn (): array => [
                    'enabled' => $this->enabled,
                    'displayUrl' => $this->displayUrl,
                    'attendanceUrl' => AttendancePage::getUrl(['mode' => 'live']),
                    'hint' => CrmHint::get('attendance.display'),
                ]),
        ]);
    }
}
