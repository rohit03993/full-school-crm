<?php

namespace App\Filament\Concerns;

use App\Enums\EnrolledCallPurpose;
use App\Enums\LicenseFeature;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Services\CallLogService;
use App\Support\FeatureGate;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

trait HandlesLogCallModal
{
    public bool $showLogCallModal = false;

    public ?int $logCallStudentCaseId = null;

    /**
     * lead | enrolled | case
     */
    public string $logCallContext = 'lead';

    /**
     * @var array<string, mixed>
     */
    public array $logCallForm = [
        'call_direction' => 'outgoing',
        'call_connected' => true,
        'call_status' => null,
        'who_answered' => null,
        'visit_status' => null,
        'call_purpose' => null,
        'duration_minutes' => null,
        'call_notes' => null,
        'tags' => [],
        'next_followup_at' => null,
    ];

    abstract protected function pendingCallMatchesStudent(int $studentId): bool;

    abstract protected function logCallTargetStudent(): ?Student;

    #[On('open-pending-call-log')]
    public function openPendingCallLog(int $studentId): void
    {
        if (! $this->pendingCallMatchesStudent($studentId)) {
            return;
        }

        $this->js('window.CrmPendingCall.clearPending()');
        $this->openLogCallModal();
    }

    public function openLogCallModal(): void
    {
        if (! FeatureGate::enabled(LicenseFeature::Calls)) {
            Notification::make()
                ->title('Calling module is not enabled')
                ->warning()
                ->send();

            return;
        }

        $preserveCaseId = $this->logCallStudentCaseId;
        $this->resetLogCallForm();
        $this->logCallStudentCaseId = $preserveCaseId;
        $this->logCallContext = $this->resolveLogCallContext($this->logCallTargetStudent());
        $this->showLogCallModal = true;
    }

    public function closeLogCallModal(): void
    {
        $this->showLogCallModal = false;
    }

    public function updatedLogCallFormVisitStatus(?string $value): void
    {
        if ($this->logCallContext !== 'lead') {
            return;
        }

        if (! ($this->logCallForm['call_connected'] ?? false) || blank($value)) {
            return;
        }

        $visitStatus = VisitStatus::tryFrom($value);

        if ($visitStatus && in_array($visitStatus, CallLogService::FOLLOWUP_VISIT_STATUSES, true)) {
            $this->logCallForm['next_followup_at'] = app(CallLogService::class)
                ->suggestFollowUp($visitStatus, true)
                ->format('Y-m-d\TH:i');

            return;
        }

        if ($visitStatus && in_array($visitStatus, CallLogService::TERMINAL_VISIT_STATUSES, true)) {
            $this->logCallForm['next_followup_at'] = null;
        }
    }

    public function updatedLogCallFormCallPurpose(?string $value): void
    {
        if ($this->logCallContext !== 'enrolled') {
            return;
        }

        if (! ($this->logCallForm['call_connected'] ?? false) || blank($value)) {
            return;
        }

        $purpose = EnrolledCallPurpose::tryFrom($value);

        if ($purpose?->suggestsFollowUp()) {
            $this->logCallForm['next_followup_at'] = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
        }
    }

    protected function resolveLogCallContext(?Student $student): string
    {
        if ($this->logCallStudentCaseId) {
            return 'case';
        }

        if ($student && app(CallLogService::class)->isEnrolledCallContext($student)) {
            return 'enrolled';
        }

        return 'lead';
    }

    protected function resetLogCallForm(): void
    {
        $this->logCallStudentCaseId = null;
        $this->logCallContext = 'lead';
        $this->logCallForm = [
            'call_direction' => 'outgoing',
            'call_connected' => true,
            'call_status' => null,
            'who_answered' => null,
            'visit_status' => null,
            'call_purpose' => null,
            'duration_minutes' => null,
            'call_notes' => null,
            'tags' => [],
            'next_followup_at' => null,
        ];
    }

    protected function persistLogCall(Student $student, CallLogService $callLog): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Calls)) {
            Notification::make()
                ->title('Calling module is not enabled')
                ->warning()
                ->send();

            return false;
        }

        try {
            if ($this->logCallStudentCaseId) {
                $case = \App\Models\StudentCase::query()->findOrFail($this->logCallStudentCaseId);
                $callLog->logForCase($case, Auth::user(), $this->logCallForm);
            } elseif ($callLog->isEnrolledCallContext($student)) {
                $callLog->logForEnrolledStudent($student, Auth::user(), $this->logCallForm);
            } else {
                $callLog->log($student, Auth::user(), $this->logCallForm);
            }
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not log call')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                ->danger()
                ->send();

            return false;
        }

        return true;
    }
}
