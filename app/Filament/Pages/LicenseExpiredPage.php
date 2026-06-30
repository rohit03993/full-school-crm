<?php

namespace App\Filament\Pages;

use App\Services\LicenseService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class LicenseExpiredPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'license-expired';

    protected static ?string $title = 'License expired';

    protected string $view = 'filament.pages.license-expired';

    public ?string $expiresAt = null;

    public function mount(LicenseService $license): void
    {
        $this->expiresAt = optional($license->expiresAt())?->format('d M Y');
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
