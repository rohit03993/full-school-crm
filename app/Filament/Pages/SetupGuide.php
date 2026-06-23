<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SetupGuide extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Setup Guide';

    protected static ?string $title = 'Setup guide';

    protected static ?int $navigationSort = 20;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('setup.guide');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.setup-guide-content'),
        ]);
    }
}
