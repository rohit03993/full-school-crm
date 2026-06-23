<?php

namespace App\Services;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\VisitStatus;
use App\Enums\WhoAnswered;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Models\Visit;
use App\Support\CrmCacheInvalidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CallLogService
{
    public const MAX_NOT_CONNECTED_ATTEMPTS = 3;

    /**
     * @var list<VisitStatus>
     */
    public const FOLLOWUP_VISIT_STATUSES = [
        VisitStatus::Interested,
        VisitStatus::FollowUpRequired,
        VisitStatus::AdmissionReady,
    ];

    /**
     * @var list<VisitStatus>
     */
    public const TERMINAL_VISIT_STATUSES = [
        VisitStatus::NotInterested,
        VisitStatus::Joined,
    ];

    public function __construct(
        protected AuditService $audit,
        protected PostCallWhatsAppService $postCallWhatsApp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function log(Student $student, User $staff, array $data): StudentCall
    {
        $connected = (bool) ($data['call_connected'] ?? false);
        $direction = CallDirection::tryFrom((string) ($data['call_direction'] ?? 'outgoing'))
            ?? CallDirection::Outgoing;

        $rules = [
            'call_connected' => 'required|boolean',
            'call_direction' => 'nullable|in:outgoing,incoming',
            'duration_minutes' => 'nullable|integer|min:0|max:600',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'next_followup_at' => 'nullable|date',
        ];

        if ($connected) {
            $rules['who_answered'] = 'required|in:'.implode(',', array_keys(WhoAnswered::options()));
            $rules['visit_status'] = 'required|in:'.implode(',', array_column(VisitStatus::cases(), 'value'));
            $rules['call_notes'] = 'required|string|min:10|max:2000';
        } else {
            $rules['call_status'] = 'required|in:'.implode(',', array_column(CallStatus::cases(), 'value'));
            $rules['call_notes'] = 'nullable|string|max:2000';
        }

        $validated = Validator::make($data, $rules, [
            'call_notes.required' => 'Please add call notes (at least 10 characters) when the call connected.',
            'call_notes.min' => 'Call notes must be at least 10 characters when connected.',
        ])->validate();

        $callStatus = $connected
            ? CallStatus::Connected
            : CallStatus::from($validated['call_status']);

        $visitStatus = $connected
            ? VisitStatus::from($validated['visit_status'])
            : ($callStatus === CallStatus::WrongNumber ? VisitStatus::NotInterested : null);

        if ($student->is_call_blocked) {
            throw ValidationException::withMessages([
                'student' => 'This number is blocked from calling after repeated failed attempts.',
            ]);
        }

        $call = DB::transaction(function () use ($student, $staff, $validated, $connected, $direction, $callStatus, $visitStatus): StudentCall {
            $lockedStudent = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $enquiry = $this->resolveEnquiry($lockedStudent, $staff);

            if ($visitStatus && in_array($visitStatus, self::TERMINAL_VISIT_STATUSES, true)) {
                $validated['next_followup_at'] = null;
            } elseif ($this->requiresFollowUpDate($connected, $visitStatus, $callStatus, $lockedStudent)) {
                Validator::make($validated, [
                    'next_followup_at' => 'required|date|after:now',
                ], [], ['next_followup_at' => 'follow-up date'])->validate();
            }

            $nextFollowup = filled($validated['next_followup_at'] ?? null)
                ? Carbon::parse($validated['next_followup_at'])
                : $this->defaultNextFollowup($callStatus, $visitStatus, $lockedStudent);

            $call = StudentCall::query()->create([
                'student_id' => $lockedStudent->id,
                'enquiry_id' => $enquiry?->id,
                'user_id' => $staff->id,
                'call_status' => $callStatus,
                'call_direction' => $direction,
                'who_answered' => $connected ? WhoAnswered::from($validated['who_answered']) : null,
                'duration_minutes' => (int) ($validated['duration_minutes'] ?? 0),
                'call_notes' => $validated['call_notes'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'visit_status_changed_to' => $visitStatus,
                'next_followup_at' => $nextFollowup,
                'called_at' => now(),
            ]);

            $this->syncStudentSummary($lockedStudent, $call, $visitStatus, $nextFollowup);
            $this->syncEnquiryAndVisit($enquiry, $staff, $call, $visitStatus, $nextFollowup);

            $this->audit->log(
                action: 'Call Logged',
                auditable: $call,
                newValues: [
                    'student_id' => $lockedStudent->id,
                    'call_status' => $callStatus->value,
                    'staff_user_id' => $staff->id,
                ],
                user: $staff,
            );

            return $call->load(['staff', 'enquiry.course']);
        });

        $this->postCallWhatsApp->maybeQueueAfterConnectedCall($call, $staff);

        CrmCacheInvalidator::afterCallLogged(
            $staff->id,
            $call->enquiry?->meeting_with_user_id,
        );

        return $call;
    }

    public function suggestFollowUp(?VisitStatus $visitStatus, bool $connected): Carbon
    {
        $now = now();

        if ($connected && $visitStatus && in_array($visitStatus, self::FOLLOWUP_VISIT_STATUSES, true)) {
            $suggested = $now->copy()->addDays(2)->setTime(10, 0);
        } elseif (! $connected) {
            $suggested = $now->copy()->addHours(2)->minute(0)->second(0);
        } else {
            $suggested = $now->copy()->addDay()->setTime(10, 0);
        }

        return $this->clampBusinessHours($suggested->isPast() ? $now->copy()->addHour()->minute(0)->second(0) : $suggested);
    }

    protected function resolveEnquiry(Student $student, User $staff): ?Enquiry
    {
        $enquiry = $student->enquiries()
            ->whereDoesntHave('admission')
            ->latest()
            ->first();

        return $enquiry;
    }

    protected function requiresFollowUpDate(
        bool $connected,
        ?VisitStatus $visitStatus,
        CallStatus $callStatus,
        Student $student,
    ): bool {
        if ($visitStatus && in_array($visitStatus, self::TERMINAL_VISIT_STATUSES, true)) {
            return false;
        }

        if ($this->willHitNotConnectedCap($student, $callStatus)) {
            return false;
        }

        if ($connected && $visitStatus && in_array($visitStatus, self::FOLLOWUP_VISIT_STATUSES, true)) {
            return true;
        }

        return false;
    }

    protected function willHitNotConnectedCap(Student $student, CallStatus $callStatus): bool
    {
        if ($callStatus->isConnected()) {
            return false;
        }

        $failedAttempts = StudentCall::query()
            ->where('student_id', $student->id)
            ->whereIn('call_status', CallStatus::notConnectedValues())
            ->count();

        return $failedAttempts >= self::MAX_NOT_CONNECTED_ATTEMPTS;
    }

    protected function defaultNextFollowup(
        CallStatus $callStatus,
        ?VisitStatus $visitStatus,
        Student $student,
    ): ?Carbon {
        if ($visitStatus && in_array($visitStatus, self::TERMINAL_VISIT_STATUSES, true)) {
            return null;
        }

        if ($callStatus->isConnected() && $visitStatus && in_array($visitStatus, self::FOLLOWUP_VISIT_STATUSES, true)) {
            return $this->suggestFollowUp($visitStatus, true);
        }

        if (! $callStatus->isConnected()) {
            if ($this->willHitNotConnectedCap($student, $callStatus)) {
                return null;
            }

            return match ($callStatus) {
                CallStatus::NoAnswer, CallStatus::NotReachable, CallStatus::SwitchedOff => now()->addDay(),
                CallStatus::Busy => now()->addHours(2),
                CallStatus::Callback => now()->addHours(4),
                CallStatus::WrongNumber => null,
                default => null,
            };
        }

        return null;
    }

    protected function syncStudentSummary(
        Student $student,
        StudentCall $call,
        ?VisitStatus $visitStatus,
        ?Carbon $nextFollowup,
    ): void {
        $student->total_calls = (int) $student->total_calls + 1;
        $student->last_call_at = $call->called_at;
        $student->last_call_status = $call->call_status;
        $student->last_call_notes = filled($call->call_notes) ? $call->call_notes : $student->last_call_notes;
        $student->next_call_followup_at = $nextFollowup;

        if ($visitStatus === VisitStatus::NotInterested) {
            $student->next_call_followup_at = null;
            $this->applyPermanentBlock($student, 'not_interested');
        }

        if (! $call->call_status->isConnected() && $this->willHitNotConnectedCap($student, $call->call_status)) {
            $student->next_call_followup_at = null;
            $this->applyPermanentBlock($student, 'max_not_connected_attempts');
        }

        $student->save();
    }

    protected function syncEnquiryAndVisit(
        ?Enquiry $enquiry,
        User $staff,
        StudentCall $call,
        ?VisitStatus $visitStatus,
        ?Carbon $nextFollowup,
    ): void {
        if (! $enquiry) {
            return;
        }

        if ($visitStatus) {
            $enquiry->update(['latest_visit_status' => $visitStatus]);
        }

        $summary = filled($call->call_notes)
            ? $call->call_notes
            : 'Phone call — '.$call->call_status->label();

        Visit::query()->create([
            'student_id' => $call->student_id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => $call->called_at->toDateString(),
            'staff_user_id' => $staff->id,
            'discussion_summary' => $summary,
            'remarks' => $call->call_direction === CallDirection::Incoming ? 'Incoming call' : 'Outgoing call',
            'next_follow_up_date' => $nextFollowup?->toDateString(),
            'status' => $visitStatus ?? VisitStatus::FollowUpRequired,
        ]);
    }

    protected function applyPermanentBlock(Student $student, string $reason): void
    {
        $student->is_call_blocked = true;
        $student->call_blocked_reason = $reason;
        $student->call_blocked_at ??= now();
    }

    protected function clampBusinessHours(Carbon $suggested): Carbon
    {
        if ($suggested->hour < 9) {
            $suggested->setTime(9, 0);
        } elseif ($suggested->hour >= 20) {
            $suggested->addDay()->setTime(9, 0);
        }

        return $suggested;
    }
}
