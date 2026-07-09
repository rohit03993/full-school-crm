<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Enums\CourseStatus;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Courses\Concerns\SyncsCourseInstallmentTemplates;
use App\Filament\Resources\Courses\Concerns\SyncsCourseSubjects;
use App\Filament\Pages\ClassSectionsPage;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Services\CourseLifecycleService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCourse extends EditRecord
{
    use ShowsCrmPageHint;
    use SyncsCourseInstallmentTemplates;
    use SyncsCourseSubjects;

    protected static string $resource = CourseResource::class;

    protected static function crmHintKey(): ?string
    {
        return request()->query('panel') === 'subjects' ? 'courses.subjects' : 'courses.edit';
    }

    public function getTitle(): string
    {
        if (request()->query('panel') === 'subjects' && $this->record instanceof Course) {
            return 'Subjects — '.$this->record->name;
        }

        return parent::getTitle();
    }

    public function getSubheading(): ?string
    {
        if (request()->query('panel') === 'subjects') {
            return 'Shared by every section under this class. Add subjects before creating exam windows.';
        }

        return parent::getSubheading();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deactivate')
                ->label('Mark Inactive')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Mark course inactive?')
                ->modalDescription('Inactive courses are hidden from new enquiries but stay in history for existing leads and students.')
                ->visible(fn (Course $record): bool => $record->status === CourseStatus::Active)
                ->action(function (Course $record): void {
                    $record->update(['status' => CourseStatus::Inactive]);

                    Notification::make()
                        ->title('Course marked inactive')
                        ->success()
                        ->send();
                }),
            DeleteAction::make()
                ->modalHeading('Delete this class?')
                ->modalDescription('Deletes all sections and removes the class from the website and CRM when safe. Classes linked to past enquiries or students are hidden instead of fully deleted.')
                ->action(function (CourseLifecycleService $lifecycle): void {
                    try {
                        $lifecycle->deleteProgrammeWithAllSections($this->getRecord());
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Could not delete class')
                            ->body(collect($exception->errors())->flatten()->first() ?? 'This class cannot be deleted.')
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Class deleted')
                        ->success()
                        ->send();

                    $this->redirect(ClassSectionsPage::getUrl());
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ClassSectionsPage::getUrl();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->mutateFormDataBeforeFillForInstallmentTemplates($data, $this->record);

        return $this->mutateFormDataBeforeFillForCourseSubjects($data, $this->record);
    }

    protected function afterSave(): void
    {
        $state = $this->form->getState();

        $this->syncCourseInstallmentTemplates($this->record, $state);
        $this->syncCourseSubjects($this->record, $state);
    }
}
