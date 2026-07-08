<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Courses\Concerns\SyncsCourseInstallmentTemplates;
use App\Filament\Resources\Courses\Concerns\SyncsCourseSubjects;
use App\Filament\Pages\ClassSectionsPage;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourse extends CreateRecord
{
    use ShowsCrmPageHint;
    use SyncsCourseInstallmentTemplates;
    use SyncsCourseSubjects;

    protected static function crmHintKey(): ?string
    {
        return 'courses.create';
    }

    protected static string $resource = CourseResource::class;

    protected function getRedirectUrl(): string
    {
        return ClassSectionsPage::getUrl();
    }

    protected function afterCreate(): void
    {
        $state = $this->form->getState();

        $this->syncCourseInstallmentTemplates($this->record, $state);
        $this->syncCourseSubjects($this->record, $state);
    }
}
