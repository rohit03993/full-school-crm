<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Enquiries\EnquiryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEnquiries extends ListRecords
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'enquiries.list';
    }

    protected static string $resource = EnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newLead')
                ->label('New Lead')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->url(StudentSearchPage::getUrl()),
        ];
    }
}
