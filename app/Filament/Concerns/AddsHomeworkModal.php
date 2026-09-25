<?php

namespace App\Filament\Concerns;

use App\Models\Batch;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Services\HomeworkCheckService;
use App\Services\HomeworkSubmissionService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait AddsHomeworkModal
{
    abstract protected function dateString(): string;

    public function startAdd(int $batchId, int $subjectId): void
    {
        if ($batchId < 1 || $subjectId < 1) {
            return;
        }

        $this->mountAction('addHomework', [
            'batchId' => $batchId,
            'subjectId' => $subjectId,
        ]);
    }

    public function addHomeworkAction(): Action
    {
        return Action::make('addHomework')
            ->label('Add homework')
            ->modalHeading(function (array $arguments): string {
                return $this->homeworkModalExisting($arguments) ? 'Update homework' : 'Add homework';
            })
            ->modalDescription(function (array $arguments): string {
                $label = $this->homeworkModalClassSubjectLabel($arguments);

                return $label !== ''
                    ? $label
                    : 'Pick the class and subject, then type the homework or attach a file.';
            })
            ->modalSubmitActionLabel(fn (): string => $this->homeworkModalSavesAsAdmin()
                ? 'Save homework'
                : 'Submit to admin')
            ->modalWidth('lg')
            ->arguments([
                'batchId' => null,
                'subjectId' => null,
            ])
            ->fillForm(fn (array $arguments): array => $this->homeworkModalFillForm($arguments))
            ->form(fn (array $arguments): array => $this->homeworkModalForm($arguments))
            ->action(function (array $data, array $arguments): void {
                $this->saveHomeworkFromModal($data, $arguments);
            });
    }

    protected function homeworkModalAllowsPickingClass(): bool
    {
        $user = Auth::user();

        return $user !== null && app(HomeworkCheckService::class)->userCanManageHomeworkDesk($user);
    }

    protected function homeworkModalSavesAsAdmin(): bool
    {
        return $this->homeworkModalAllowsPickingClass();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return list<\Filament\Forms\Components\Component>
     */
    protected function homeworkModalForm(array $arguments): array
    {
        $prefilled = $this->homeworkModalIsPrefilled($arguments);

        $classField = $prefilled
            ? Hidden::make('batch_id')
            : Select::make('batch_id')
                ->label('Class')
                ->options(fn (): array => $this->homeworkModalBatchOptions())
                ->searchable()
                ->native(false)
                ->required()
                ->live()
                ->afterStateUpdated(function (mixed $state, callable $set): void {
                    $set('course_subject_id', null);
                });

        $subjectField = $prefilled
            ? Hidden::make('course_subject_id')
            : Select::make('course_subject_id')
                ->label('Subject')
                ->options(function (Get $get): array {
                    $batchId = (int) ($get('batch_id') ?? 0);

                    return $batchId > 0 ? $this->homeworkModalSubjectOptions($batchId) : [];
                })
                ->searchable()
                ->native(false)
                ->required()
                ->visible(fn (Get $get): bool => filled($get('batch_id')));

        return [
            $classField,
            $subjectField,
            TextInput::make('title')
                ->label('Title (optional)')
                ->placeholder('e.g. Chapter 5 – Q1 to Q10')
                ->maxLength(255),
            Textarea::make('description')
                ->label('Homework details')
                ->placeholder('Type the homework, or attach a file below.')
                ->rows(4)
                ->columnSpanFull(),
            FileUpload::make('attachment')
                ->label('PDF or image (optional)')
                ->disk('public')
                ->directory('homework')
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ])
                ->maxSize(10240)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function homeworkModalFillForm(array $arguments): array
    {
        $existing = $this->homeworkModalExisting($arguments);

        return [
            'batch_id' => $this->homeworkModalBatchId($arguments) ?: null,
            'course_subject_id' => $this->homeworkModalSubjectId($arguments) ?: null,
            'title' => (string) ($existing?->title ?? ''),
            'description' => (string) ($existing?->description ?? ''),
            'attachment' => $existing?->file_path,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $arguments
     */
    protected function saveHomeworkFromModal(array $data, array $arguments): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $batchId = (int) ($data['batch_id'] ?? $arguments['batchId'] ?? 0);
        $subjectId = (int) ($data['course_subject_id'] ?? $arguments['subjectId'] ?? 0);

        if ($batchId < 1 || $subjectId < 1) {
            Notification::make()->title('Pick a class and subject first')->warning()->send();

            return;
        }

        try {
            $assignment = app(HomeworkSubmissionService::class)->submit($user, [
                'batch_id' => $batchId,
                'course_subject_id' => $subjectId,
                'homework_date' => $this->dateString(),
                'title' => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'file_path' => $this->homeworkModalAttachmentPath($data['attachment'] ?? null),
            ], asAdmin: $this->homeworkModalSavesAsAdmin());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not submit.';
            Notification::make()->title((string) $message)->warning()->send();

            throw new Halt;
        }

        $subjectLabel = $assignment->courseSubject?->displayLabel() ?? 'Homework';
        $dateLabel = Carbon::parse($this->dateString())->format('d M Y');

        if ($this->homeworkModalSavesAsAdmin()) {
            Notification::make()
                ->title('Homework saved')
                ->body($subjectLabel.' is ready to send for '.$dateLabel.'.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Submitted to admin')
            ->body($subjectLabel.' homework submitted for '.$dateLabel.'. Admin will review and send it to parents.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, string>
     */
    protected function homeworkModalBatchOptions(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $service = app(HomeworkSubmissionService::class);

        return $this->homeworkModalAllowsPickingClass()
            ? $service->allBatchOptions()
            : $service->batchOptionsFor($user);
    }

    /**
     * @return array<int, string>
     */
    protected function homeworkModalSubjectOptions(int $batchId): array
    {
        $user = Auth::user();

        if (! $user || $batchId < 1) {
            return [];
        }

        $service = app(HomeworkSubmissionService::class);

        return $this->homeworkModalAllowsPickingClass()
            ? $service->allSubjectOptionsForBatch($batchId)
            : $service->subjectOptionsForBatch($user, $batchId);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function homeworkModalIsPrefilled(array $arguments): bool
    {
        return $this->homeworkModalBatchId($arguments) > 0
            && $this->homeworkModalSubjectId($arguments) > 0;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function homeworkModalBatchId(array $arguments): int
    {
        return (int) ($arguments['batchId'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function homeworkModalSubjectId(array $arguments): int
    {
        return (int) ($arguments['subjectId'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function homeworkModalExisting(array $arguments): ?HomeworkAssignment
    {
        $batchId = $this->homeworkModalBatchId($arguments);
        $subjectId = $this->homeworkModalSubjectId($arguments);

        if ($batchId < 1 || $subjectId < 1) {
            return null;
        }

        return HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->where('course_subject_id', $subjectId)
            ->whereDate('homework_date', $this->dateString())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function homeworkModalClassSubjectLabel(array $arguments): string
    {
        $batchId = $this->homeworkModalBatchId($arguments);
        $subjectId = $this->homeworkModalSubjectId($arguments);

        if ($batchId < 1 || $subjectId < 1) {
            return '';
        }

        $batch = Batch::query()->find($batchId);
        $subject = CourseSubject::query()->find($subjectId);

        if (! $batch || ! $subject) {
            return '';
        }

        return $batch->displayLabel().' · '.$subject->displayLabel();
    }

    protected function homeworkModalAttachmentPath(mixed $attachment): ?string
    {
        if (is_array($attachment)) {
            $first = $attachment[array_key_first($attachment)] ?? reset($attachment);

            return filled($first) ? (string) $first : null;
        }

        return filled($attachment) ? (string) $attachment : null;
    }
}
