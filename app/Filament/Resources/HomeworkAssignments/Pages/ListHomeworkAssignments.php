<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeworkAssignments extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = HomeworkAssignmentResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'homework.list';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Upload homework'),
        ];
    }
}
