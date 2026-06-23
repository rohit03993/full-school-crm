<?php

namespace App\Filament\Concerns;

use App\Enums\VisitStatus;
use App\Models\Student;
use App\Services\CallLogService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

trait HandlesLogCallModal
{
    public bool $showLogCallModal = false;

    /**
     * @var array<string, mixed>
     */
    public array $logCallForm = [
        'call_direction' => 'outgoing',
        'call_connected' => true,
        'call_status' => null,
        'who_answered' => null,
        'visit_status' => null,
        'duration_minutes' => null,
        'call_notes' => null,
        'tags' => [],
        'next_followup_at' => null,
    ];

    abstract protected function pendingCallMatchesStudent(int $studentId): bool;

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
        $this->resetLogCallForm();
        $this->showLogCallModal = true;
    }

    public function closeLogCallModal(): void
    {
        $this->showLogCallModal = false;
    }

    public function updatedLogCallFormVisitStatus(?string $value): void
    {
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

    protected function resetLogCallForm(): void
    {
        $this->logCallForm = [
            'call_direction' => 'outgoing',
            'call_connected' => true,
            'call_status' => null,
            'who_answered' => null,
            'visit_status' => null,
            'duration_minutes' => null,
            'call_notes' => null,
            'tags' => [],
            'next_followup_at' => null,
        ];
    }

    protected function persistLogCall(Student $student, CallLogService $callLog): bool
    {
        try {
            $callLog->log($student, Auth::user(), $this->logCallForm);
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
