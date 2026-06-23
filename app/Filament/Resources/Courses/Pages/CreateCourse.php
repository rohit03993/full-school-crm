<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourse extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'courses.create';
    }

    protected static string $resource = CourseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
