<?php

namespace App\Filament\Resources\ActivityTypes\Pages;

use App\Enums\RoleName;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\SessionAttendancePage;
use App\Filament\Resources\ActivityTypes\ActivityTypeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListActivityTypes extends ListRecords
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'activity.types';
    }

    protected static string $resource = ActivityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markSessionAttendance')
                ->label('Mark workshop / event attendance')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info')
                ->url(SessionAttendancePage::getUrl()),
            CreateAction::make()
                ->visible(fn (): bool => Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false),
        ];
    }
}
