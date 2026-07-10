<?php

namespace App\Filament\Concerns;

use App\Enums\CampusVisitOutcome;
use App\Enums\CampusVisitPurpose;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\LeadAssignmentService;
use App\Services\StudentCaseService;
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

    public string $closeMeetingCallingMode = 'self';

    public ?int $closeMeetingCallingStaffId = null;

    public string $closeMeetingCallingHandoffNote = '';

    public string $closeMeetingResolutionMode = 'resolved';

    public ?string $closeMeetingCaseType = null;

    public string $closeMeetingCaseTitle = '';

    public ?int $closeMeetingCaseAssigneeId = null;

    public string $closeMeetingCaseHandoffNote = '';

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
        $this->closeMeetingCallingMode = 'self';
        $this->closeMeetingCallingStaffId = null;
        $this->closeMeetingCallingHandoffNote = '';
        $this->closeMeetingResolutionMode = 'resolved';
        $this->closeMeetingCaseType = CampusVisitPurpose::General->value;
        $this->closeMeetingCaseTitle = '';
        $this->closeMeetingCaseAssigneeId = null;
        $this->closeMeetingCaseHandoffNote = '';
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
        $visitStatus = $isEnrolled ? null : VisitStatus::tryFrom((string) $this->closeMeetingStatus);
        $campusOutcome = $isEnrolled
            ? $this->resolveEnrolledCampusOutcome()
            : null;

        try {
            $assignment = $service->close(
                $assignment,
                Auth::user(),
                $this->closeMeetingNotes,
                $visitStatus,
                $campusOutcome,
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not close meeting')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                ->danger()
                ->send();

            return;
        }

        if (! $isEnrolled && $this->shouldAssignCallingAfterMeetingClose($visitStatus)) {
            try {
                $this->assignCallingAfterMeetingClose($assignment, $visitStatus);
            } catch (ValidationException $exception) {
                Notification::make()
                    ->title('Meeting closed — calling assignment failed')
                    ->body(collect($exception->errors())->flatten()->first() ?? 'Please assign for calling from the lead list.')
                    ->warning()
                    ->send();
            }
        }

        if ($isEnrolled && $this->closeMeetingResolutionMode === 'open_case') {
            try {
                $this->openCaseAfterMeetingClose($assignment->loadMissing(['resultingVisit', 'student']));
            } catch (ValidationException $exception) {
                Notification::make()
                    ->title('Meeting closed — case could not be opened')
                    ->body(collect($exception->errors())->flatten()->first() ?? 'Please open a case from the student profile.')
                    ->warning()
                    ->send();
            }
        }

        $successMessage = $this->closeMeetingSuccessMessage($isEnrolled, $visitStatus);

        $this->showCloseMeetingModal = false;
        $this->resetCloseMeetingForm();

        if (method_exists($this, 'afterMeetingClosed')) {
            $this->afterMeetingClosed();
        }

        Notification::make()
            ->title('Meeting closed')
            ->body($successMessage)
            ->success()
            ->send();
    }

    protected function shouldAssignCallingAfterMeetingClose(?VisitStatus $visitStatus): bool
    {
        if ($this->closeMeetingCallingMode === 'none') {
            return false;
        }

        if ($visitStatus && in_array($visitStatus, CallLogService::TERMINAL_VISIT_STATUSES, true)) {
            return false;
        }

        return true;
    }

    protected function assignCallingAfterMeetingClose(
        \App\Models\VisitMeetingAssignment $assignment,
        ?VisitStatus $visitStatus,
    ): void {
        $enquiry = $assignment->enquiry ?? $assignment->student->enquiries()->latest()->first();

        if (! $enquiry) {
            return;
        }

        $staff = match ($this->closeMeetingCallingMode) {
            'staff' => User::query()->findOrFail((int) $this->closeMeetingCallingStaffId),
            default => Auth::user(),
        };

        $handoffNote = filled($this->closeMeetingCallingHandoffNote)
            ? trim($this->closeMeetingCallingHandoffNote)
            : trim($this->closeMeetingNotes);

        app(LeadAssignmentService::class)->assignForCalling(
            $enquiry,
            $staff,
            Auth::user(),
            $handoffNote,
            requireHandoffNote: $this->closeMeetingCallingMode === 'staff',
        );
    }

    protected function closeMeetingSuccessMessage(bool $isEnrolled, ?VisitStatus $visitStatus): string
    {
        if ($isEnrolled) {
            if ($this->closeMeetingResolutionMode === 'open_case') {
                return 'Meeting notes saved and a support case was opened for follow-up.';
            }

            return 'Your meeting notes were saved.';
        }

        if (! $this->shouldAssignCallingAfterMeetingClose($visitStatus)) {
            return 'Your meeting notes were saved.';
        }

        return match ($this->closeMeetingCallingMode) {
            'staff' => 'Meeting notes saved and the lead was assigned for telecalling.',
            'self' => 'Meeting notes saved. This lead is now in your call queue.',
            default => 'Your meeting notes were saved.',
        };
    }

    protected function resolveEnrolledCampusOutcome(): CampusVisitOutcome
    {
        if ($this->closeMeetingResolutionMode === 'open_case') {
            return CampusVisitOutcome::Referred;
        }

        return CampusVisitOutcome::tryFrom((string) $this->closeMeetingCampusOutcome)
            ?? CampusVisitOutcome::Resolved;
    }

    protected function openCaseAfterMeetingClose(\App\Models\VisitMeetingAssignment $assignment): void
    {
        $caseType = CampusVisitPurpose::tryFrom((string) $this->closeMeetingCaseType)
            ?? CampusVisitPurpose::General;

        $title = filled($this->closeMeetingCaseTitle)
            ? trim($this->closeMeetingCaseTitle)
            : \Illuminate\Support\Str::limit(trim($this->closeMeetingNotes), 80, '…');

        $assignee = User::query()->findOrFail((int) $this->closeMeetingCaseAssigneeId);

        app(StudentCaseService::class)->open(
            $assignment->student,
            $caseType,
            $title,
            trim($this->closeMeetingNotes),
            $assignee,
            Auth::user(),
            trim($this->closeMeetingCaseHandoffNote),
            $assignment->resultingVisit,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function closeMeetingCaseTypeOptions(): array
    {
        return CampusVisitPurpose::options();
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

    /**
     * @return array<int, string>
     */
    protected function closeMeetingCallingStaffOptions(): array
    {
        return LeadAssignmentService::activeStaffOptions();
    }
}
