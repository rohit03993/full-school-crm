<?php

namespace App\Filament\Resources\IndustrialVisits\Pages;

use App\Enums\ActivityKind;
use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Resources\IndustrialVisits\IndustrialVisitResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditIndustrialVisit extends EditRecord
{
    protected static string $resource = IndustrialVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAttendance')
                ->label('Mark Attendance')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->url(fn (): string => ActivityAttendancePage::getUrl()
                    .'?kind='.ActivityKind::IndustrialVisit->value.'&id='.$this->record->id),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
