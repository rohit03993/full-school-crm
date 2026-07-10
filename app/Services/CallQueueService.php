<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Support\CrmPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CallQueueService
{
    public function __construct(
        protected CallLogService $callLog,
    ) {}

    /**
     * Leads assigned for calling that are due today, overdue, or uncalled.
     *
     * @return EloquentCollection<int, Student>
     */
    public function dueQueue(User $staff, ?int $limit = null): EloquentCollection
    {
        $limit ??= CrmPagination::PER_PAGE;

        return $this->dueQueueQuery($staff)
            ->with(['enquiries' => fn ($query) => $query->latest()->limit(1), 'enquiries.course', 'enquiries.callingAssignedBy'])
            ->withCount([
                'calls as not_connected_attempts_count' => fn ($query) => $query->whereIn(
                    'call_status',
                    CallStatus::notConnectedValues(),
                ),
            ])
            ->limit($limit)
            ->get();
    }

    public function dueQueueCount(User $staff): int
    {
        return $this->dueQueueQuery($staff)->count();
    }

    /**
     * @return EloquentCollection<int, Student>
     */
    public function scheduledQueue(User $staff, ?int $limit = null): EloquentCollection
    {
        $limit ??= CrmPagination::PER_PAGE;

        return $this->scheduledQueueQuery($staff)
            ->with(['enquiries' => fn ($query) => $query->latest()->limit(1), 'enquiries.course', 'enquiries.callingAssignedBy'])
            ->withCount([
                'calls as not_connected_attempts_count' => fn ($query) => $query->whereIn(
                    'call_status',
                    CallStatus::notConnectedValues(),
                ),
            ])
            ->limit($limit)
            ->get();
    }

    public function scheduledQueueCount(User $staff): int
    {
        return $this->scheduledQueueQuery($staff)->count();
    }

    /**
     * @deprecated Use dueQueue() — kept for backward compatibility in tests/widgets.
     *
     * @return EloquentCollection<int, Student>
     */
    public function todayQueue(User $staff, ?int $limit = null): EloquentCollection
    {
        return $this->dueQueue($staff, $limit);
    }

    public function todayQueueCount(User $staff): int
    {
        return $this->dueQueueCount($staff);
    }

    /**
     * @return Builder<Student>
     */
    protected function assignedLeadQuery(User $staff): Builder
    {
        return Student::query()
            ->where('is_call_blocked', false)
            ->whereNotIn('id', $this->studentIdsExcludedByNotConnectedCap())
            ->whereHas('enquiries', function ($query) use ($staff): void {
                $query->where('meeting_with_user_id', $staff->id)
                    ->whereNotNull('calling_assigned_at');
            });
    }

    /**
     * Callable now: uncalled assigned leads, overdue callbacks, or due-today callbacks not yet called today.
     *
     * @return Builder<Student>
     */
    protected function dueQueueQuery(User $staff): Builder
    {
        $today = today();

        return $this->assignedLeadQuery($staff)
            ->where(function ($query) use ($today): void {
                $query->where(function ($inner): void {
                    $inner->whereNull('next_call_followup_at')
                        ->where('total_calls', 0);
                })->orWhere(function ($inner) use ($today): void {
                    $inner->whereNotNull('next_call_followup_at')
                        ->whereDate('next_call_followup_at', '<', $today);
                })->orWhere(function ($inner) use ($today): void {
                    $inner->whereNotNull('next_call_followup_at')
                        ->whereDate('next_call_followup_at', $today)
                        ->where(function ($called) use ($today): void {
                            $called->whereNull('last_call_at')
                                ->orWhereDate('last_call_at', '<', $today);
                        });
                });
            })
            ->orderByRaw('
                CASE
                    WHEN next_call_followup_at IS NOT NULL AND DATE(next_call_followup_at) < ? THEN 1
                    WHEN next_call_followup_at IS NOT NULL AND DATE(next_call_followup_at) = ? THEN 2
                    WHEN total_calls = 0 THEN 3
                    ELSE 4
                END
            ', [$today->toDateString(), $today->toDateString()])
            ->orderBy('next_call_followup_at')
            ->orderBy('created_at');
    }

    /**
     * Future-dated callbacks — visible for planning but not in the active call queue.
     *
     * @return Builder<Student>
     */
    protected function scheduledQueueQuery(User $staff): Builder
    {
        return $this->assignedLeadQuery($staff)
            ->whereNotNull('next_call_followup_at')
            ->whereDate('next_call_followup_at', '>', today())
            ->orderBy('next_call_followup_at')
            ->orderBy('name');
    }

    /**
     * @return array{calls_today: int, connected_today: int, pending_followups: int, queue_count: int, scheduled_count: int}
     */
    public function todayStats(User $staff): array
    {
        $today = today();

        return [
            'calls_today' => StudentCall::query()
                ->where('user_id', $staff->id)
                ->whereDate('called_at', $today)
                ->count(),
            'connected_today' => StudentCall::query()
                ->where('user_id', $staff->id)
                ->whereDate('called_at', $today)
                ->where('call_status', CallStatus::Connected)
                ->count(),
            'pending_followups' => $this->dueQueueCount($staff),
            'queue_count' => $this->dueQueueCount($staff),
            'scheduled_count' => $this->scheduledQueueCount($staff),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function leadPayload(Student $student): array
    {
        /** @var Enquiry|null $enquiry */
        $enquiry = $student->enquiries->first();
        $mobile = $student->dialableMobile();
        $status = $enquiry?->latest_visit_status;
        $followUp = $student->next_call_followup_at;

        return [
            'id' => $student->id,
            'name' => $student->name,
            'father_name' => $student->father_name,
            'mobile_display' => $mobile ? '+91'.substr($mobile, -10) : '—',
            'mobile_raw' => $mobile,
            'course' => $enquiry?->course?->name ?? 'Not decided',
            'status_label' => $status?->label() ?? 'New lead',
            'total_calls' => (int) $student->total_calls,
            'last_call_notes' => $student->last_call_notes,
            'last_call_at' => $student->last_call_at?->format('d M Y, h:i A'),
            'next_followup_at' => $followUp?->format('d M Y, h:i A'),
            'next_followup_date' => $followUp?->format('d M Y'),
            'next_followup_time' => $followUp?->format('h:i A'),
            'follow_up_queue_label' => $this->followUpQueueLabel($followUp, (int) $student->total_calls),
            'assigned_by_name' => $enquiry?->callingAssignedBy?->name,
            'assigned_at_label' => $enquiry?->calling_assigned_at?->format('d M Y, h:i A'),
            'calling_handoff_note' => $enquiry?->calling_handoff_note,
            'is_overdue' => $followUp !== null && $followUp->toDateString() < today()->toDateString(),
            'is_due_today' => $followUp?->isToday() ?? false,
            'is_scheduled_future' => $followUp !== null && $followUp->toDateString() > today()->toDateString(),
            'not_connected_attempts_count' => (int) ($student->not_connected_attempts_count ?? 0),
            'profile_url' => StudentProfilePage::getUrl(['record' => $student->id]),
        ];
    }

    public function followUpQueueLabel(?Carbon $followUp, int $totalCalls = 0): ?string
    {
        if ($followUp === null) {
            return $totalCalls === 0 ? 'New — not called yet' : null;
        }

        if ($followUp->toDateString() < today()->toDateString()) {
            $days = (int) $followUp->diffInDays(today());

            return $days === 1 ? 'Overdue by 1 day' : "Overdue by {$days} days";
        }

        if ($followUp->isToday()) {
            return 'Due today · '.$followUp->format('h:i A');
        }

        $days = (int) today()->diffInDays($followUp->toDateString());

        return ($days === 1 ? 'Scheduled tomorrow' : "Scheduled in {$days} days")
            .' · '.$followUp->format('d M Y, h:i A');
    }

    /**
     * @return Collection<int, int>
     */
    protected function studentIdsExcludedByNotConnectedCap(): Collection
    {
        return StudentCall::query()
            ->select('student_id')
            ->whereIn('call_status', CallStatus::notConnectedValues())
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) >= ?', [CallLogService::MAX_NOT_CONNECTED_ATTEMPTS])
            ->pluck('student_id');
    }
}
