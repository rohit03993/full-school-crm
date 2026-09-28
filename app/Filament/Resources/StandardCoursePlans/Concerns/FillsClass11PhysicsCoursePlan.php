<?php

namespace App\Filament\Resources\StandardCoursePlans\Concerns;

use App\Enums\StandardCoursePlanStatus;
use App\Support\Class11CbsePhysicsStarter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait FillsClass11PhysicsCoursePlan
{
    public function fillClass11Physics(): void
    {
        $current = is_array($this->data) ? $this->data : [];

        $this->form->fill([
            'academic_session_id' => $current['academic_session_id'] ?? null,
            'course_id' => $current['course_id'] ?? null,
            'course_subject_id' => $current['course_subject_id'] ?? null,
            'status' => $current['status'] ?? StandardCoursePlanStatus::Draft->value,
            'chapters' => Class11CbsePhysicsStarter::formChapters(),
            'practicals' => Class11CbsePhysicsStarter::formPracticals(),
        ]);

        Notification::make()
            ->title('Class 11 Physics filled')
            ->body('Check the chapters, marks, and practicals. Change anything you want, then save.')
            ->success()
            ->send();
    }

    protected function fillClass11PhysicsAction(): Action
    {
        return Action::make('fillClass11Physics')
            ->label('Fill Class 11 Physics')
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Fill Class 11 Physics')
            ->modalDescription('This puts the CBSE Class 11 Physics chapters, topic times, estimated marks, and practicals on this screen. The year, programme, and subject stay as you picked them.')
            ->modalSubmitActionLabel('Fill')
            ->action(fn () => $this->fillClass11Physics());
    }
}
