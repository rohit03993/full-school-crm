<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourses extends ListRecords
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'courses.list';
    }

    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Course'),
        ];
    }
}
