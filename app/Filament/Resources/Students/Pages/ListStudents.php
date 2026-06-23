<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStudents extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = StudentResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'students.list';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('searchStudent')
                ->label('Search Student')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->url(StudentSearchPage::getUrl())
                ->color('gray'),
        ];
    }
}
