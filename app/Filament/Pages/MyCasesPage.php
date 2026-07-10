<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Support\CrmAccess;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MyCasesPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.my-cases-redirect';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        return CrmAccess::can($user, CrmPermission::CasesView);
    }

    public function mount(): void
    {
        $this->redirect(MyMeetingsPage::getUrl(['tab' => 'my_cases']), navigate: true);
    }
}
