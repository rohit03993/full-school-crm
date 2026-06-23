<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Batches\BatchResource;
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

    protected static string $resource = BatchResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'batches.edit';
    }

    protected function getHeaderActions(): array
    {
        return [
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
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
