<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Enums\CourseStatus;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCourse extends EditRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = CourseResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'courses.edit';
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
                ->before(function (DeleteAction $action, Course $record): void {
                    $check = $record->deletionBlockReason();

                    if ($check['can_delete']) {
                        return;
                    }

                    Notification::make()
                        ->title('Cannot delete this course')
                        ->body($check['reason'])
                        ->warning()
                        ->persistent()
                        ->send();

                    $action->halt();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
