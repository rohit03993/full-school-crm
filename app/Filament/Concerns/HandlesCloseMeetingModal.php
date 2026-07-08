<?php

namespace App\Filament\Concerns;

use App\Enums\CampusVisitOutcome;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Services\VisitMeetingAssignmentService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait HandlesCloseMeetingModal
{
    public bool $showCloseMeetingModal = false;

    public string $closeMeetingNotes = '';

    public ?string $closeMeetingCampusOutcome = null;

    public ?string $closeMeetingStatus = null;

    abstract protected function studentForCloseMeeting(): Student;

    public function openCloseMeetingModal(): void
    {
        $assignment = app(VisitMeetingAssignmentService::class)
            ->profileMeetingAssignment($this->studentForCloseMeeting(), Auth::user());

        if (! ($assignment['can_close'] ?? false)) {
            Notification::make()
                ->title('Cannot close meeting')
                ->body('Only the assigned staff member can close this meeting.')
                ->warning()
                ->send();

            return;
        }

        $this->resetCloseMeetingForm();
        $this->showCloseMeetingModal = true;
    }

    public function cancelCloseMeetingModal(): void
    {
        $this->showCloseMeetingModal = false;
    }

    protected function resetCloseMeetingForm(): void
    {
        $this->closeMeetingNotes = '';
        $this->closeMeetingCampusOutcome = CampusVisitOutcome::Resolved->value;
        $this->closeMeetingStatus = VisitStatus::Interested->value;
    }

    public function submitCloseMeeting(): void
    {
        $student = $this->studentForCloseMeeting();
        $service = app(VisitMeetingAssignmentService::class);
        $assignment = $service->openForStudent($student);

        if (! $assignment) {
            Notification::make()
                ->title('No open meeting')
                ->body('This meeting assignment is no longer open.')
                ->warning()
                ->send();

            $this->showCloseMeetingModal = false;

            return;
        }

        $isEnrolled = $student->activeEnrollment !== null;

        try {
            $service->close(
                $assignment,
                Auth::user(),
                $this->closeMeetingNotes,
                $isEnrolled ? null : VisitStatus::tryFrom((string) $this->closeMeetingStatus),
                $isEnrolled ? CampusVisitOutcome::tryFrom((string) $this->closeMeetingCampusOutcome) : null,
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not close meeting')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                ->danger()
                ->send();

            return;
        }

        $this->showCloseMeetingModal = false;
        $this->resetCloseMeetingForm();

        if (method_exists($this, 'afterMeetingClosed')) {
            $this->afterMeetingClosed();
        }

        Notification::make()
            ->title('Meeting closed')
            ->body('Your meeting notes were saved.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    protected function closeMeetingCampusOutcomeOptions(): array
    {
        return CampusVisitOutcome::options();
    }

    /**
     * @return array<string, string>
     */
    protected function closeMeetingVisitStatusOptions(): array
    {
        return collect(VisitStatus::cases())
            ->mapWithKeys(fn (VisitStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
