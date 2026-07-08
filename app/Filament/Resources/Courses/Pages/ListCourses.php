<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Pages\AddClassSectionPage;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions\Action;
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
            Action::make('addClassSection')
                ->label('Add class & section')
                ->url(AddClassSectionPage::getUrl())
                ->color('primary')
                ->icon('heroicon-o-plus-circle'),
            CreateAction::make()
                ->label('Add programme only'),
        ];
    }
}
