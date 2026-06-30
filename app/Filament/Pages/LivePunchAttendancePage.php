<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * @deprecated Use {@see AttendancePage} with live mode.
 */
class LivePunchAttendancePage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'live-punch-attendance';

    public function mount(): void
    {
        $this->redirect(AttendancePage::getUrl(['mode' => 'live']), navigate: true);
    }
}
