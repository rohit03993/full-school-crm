<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Pages\ClassSectionsPage;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Batches\BatchResource;
use App\Filament\Resources\Batches\Concerns\SyncsBatchStaffAssignments;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Batch;
use App\Models\CourseSubject;
use App\Models\Student;
use App\Services\BatchService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditBatch extends EditRecord
{
    use ShowsCrmPageHint;
    use SyncsBatchStaffAssignments;

    protected static string $resource = BatchResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'batches.edit';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageSubjects')
                ->label(fn (): string => CourseSubject::query()
                    ->where('course_id', $this->record->course_id)
                    ->active()
                    ->exists()
                    ? 'Manage subjects'
                    : 'Add subjects')
                ->icon('heroicon-o-book-open')
                ->color(fn (): string => CourseSubject::query()
                    ->where('course_id', $this->record->course_id)
                    ->active()
                    ->exists()
                    ? 'gray'
                    : 'warning')
                ->url(fn (): string => CourseResource::getUrl('edit', ['record' => $this->record->course_id]).'?panel=subjects'),
            Action::make('assignStudents')
                ->label('Assign Students')
                ->icon(Heroicon::OutlinedUserPlus)
                ->form([
                    Select::make('student_ids')
                        ->label('Students')
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => Student::query()
                            ->whereHas('activeEnrollment', fn ($query) => $query
                                ->where('course_id', $this->record->course_id))
                            ->whereDoesntHave('activeBatchStudent', fn ($query) => $query
                                ->where('batch_id', $this->record->id))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->helperText('Enrolled students for this course who are not already in this batch.')
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data, BatchService $batches): void {
                    $count = $batches->bulkAssign(
                        $this->record,
                        $data['student_ids'] ?? [],
                        Auth::user(),
                    );

                    Notification::make()
                        ->title($count > 0 ? 'Students assigned' : 'No changes')
                        ->body($count > 0
                            ? "{$count} student(s) added to {$this->record->name}."
                            : 'Selected students are already in this batch.')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->isActive()),
            DeleteAction::make()
                ->modalDescription(fn (Batch $record): string => $record->deletionBlockReason()['can_delete']
                    ? 'This removes the section, student assignments, exam windows, and draft results linked to it. Published results block deletion.'
                    : ($record->deletionBlockReason()['reason'] ?? 'This section cannot be deleted.'))
                ->before(function (DeleteAction $action, Batch $record): void {
                    $check = $record->deletionBlockReason();

                    if ($check['can_delete']) {
                        return;
                    }

                    Notification::make()
                        ->title('Cannot delete this section')
                        ->body($check['reason'])
                        ->warning()
                        ->persistent()
                        ->send();

                    $action->halt();
                })
                ->action(function (BatchService $batches): void {
                    try {
                        $batches->deleteSection($this->getRecord());
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        Notification::make()
                            ->title('Cannot delete this section')
                            ->body(collect($exception->errors())->flatten()->first() ?? 'This section cannot be deleted.')
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Section deleted')
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
        return $this->mutateFormDataBeforeFillForStaffAssignments($data, $this->record);
    }

    protected function afterSave(): void
    {
        $this->syncBatchStaffAssignments($this->record, $this->form->getState());
    }
}
