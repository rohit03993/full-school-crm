<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\BatchAttendancePage;
use App\Filament\Resources\Batches\BatchResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBatches extends ListRecords
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'batches.list';
    }

    protected static string $resource = BatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAttendance')
                ->label('Mark Attendance')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->url(BatchAttendancePage::getUrl())
                ->color('gray'),
            CreateAction::make()
                ->label('Add Batch'),
        ];
    }
}
