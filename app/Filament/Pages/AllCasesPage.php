<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Support\CrmAccess;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AllCasesPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.all-cases-redirect';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        return CrmAccess::can($user, CrmPermission::CasesViewAll);
    }

    public function mount(): void
    {
        $this->redirect(MyMeetingsPage::getUrl(['tab' => 'all_cases']), navigate: true);
    }
}
