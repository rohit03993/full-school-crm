<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditActivitySession extends EditRecord
{
    protected static string $resource = ActivitySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAttendance')
                ->label('Mark Attendance')
                ->icon('heroicon-o-clipboard-document-check')
                ->url(fn (): string => ActivityAttendancePage::getUrl()
                    .'?id='.$this->record->id),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['metadata'] = collect($data['metadata'] ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();

        return $data;
    }
}
