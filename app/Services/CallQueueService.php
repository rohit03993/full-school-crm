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
use Illuminate\Support\Collection;

class CallQueueService
{
    public function __construct(
        protected CallLogService $callLog,
    ) {}

    /**
     * @return EloquentCollection<int, Student>
     */
    public function todayQueue(User $staff, ?int $limit = null): EloquentCollection
    {
        $limit ??= CrmPagination::PER_PAGE;

        return $this->todayQueueQuery($staff)
            ->with(['enquiries' => fn ($query) => $query->latest()->limit(1), 'enquiries.course'])
            ->withCount([
                'calls as not_connected_attempts_count' => fn ($query) => $query->whereIn(
                    'call_status',
                    CallStatus::notConnectedValues(),
                ),
            ])
            ->limit($limit)
            ->get();
    }

    public function todayQueueCount(User $staff): int
    {
        return $this->todayQueueQuery($staff)->count();
    }

    /**
     * @return Builder<Student>
     */
    protected function todayQueueQuery(User $staff): Builder
    {
        $today = today();
        $endOfToday = $today->copy()->endOfDay();
        $excludedIds = $this->studentIdsExcludedByNotConnectedCap();

        return Student::query()
            ->where('is_call_blocked', false)
            ->whereNotIn('id', $excludedIds)
            ->whereHas('enquiries', function ($query) use ($staff): void {
                $query->where('meeting_with_user_id', $staff->id)
                    ->whereNotNull('calling_assigned_at');
            })
            ->where(function ($query) use ($endOfToday): void {
                $query->whereNull('next_call_followup_at')
                    ->orWhere('next_call_followup_at', '<=', $endOfToday);
            })
            ->where(function ($query) use ($today): void {
                $query->where('total_calls', 0)
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('next_call_followup_at')
                            ->where('next_call_followup_at', '<', now());
                    })
                    ->orWhere(function ($inner) use ($today): void {
                        $inner->whereNotNull('next_call_followup_at')
                            ->whereDate('next_call_followup_at', $today)
                            ->where(function ($calledToday) use ($today): void {
                                $calledToday->whereNull('last_call_at')
                                    ->orWhereDate('last_call_at', '<', $today);
                            });
                    })
                    ->orWhere(function ($inner) use ($today): void {
                        $inner->where('total_calls', '>', 0)
                            ->where(function ($calledToday) use ($today): void {
                                $calledToday->whereNull('last_call_at')
                                    ->orWhereDate('last_call_at', '<', $today);
                            });
                    });
            })
            ->orderByRaw('
                CASE
                    WHEN next_call_followup_at IS NOT NULL AND next_call_followup_at < NOW() THEN 1
                    WHEN next_call_followup_at IS NOT NULL AND DATE(next_call_followup_at) = ? THEN 2
                    WHEN total_calls = 0 THEN 3
                    ELSE 4
                END
            ', [$today->toDateString()])
            ->orderBy('next_call_followup_at')
            ->orderBy('created_at');
    }

    /**
     * @return array{calls_today: int, connected_today: int, pending_followups: int, queue_count: int}
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
            'pending_followups' => Student::query()
                ->where('is_call_blocked', false)
                ->whereNotNull('next_call_followup_at')
                ->where('next_call_followup_at', '<=', now())
                ->whereHas('enquiries', fn ($query) => $query
                    ->where('meeting_with_user_id', $staff->id)
                    ->whereNotNull('calling_assigned_at'))
                ->count(),
            'queue_count' => $this->todayQueueCount($staff),
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
            'next_followup_at' => $student->next_call_followup_at?->format('d M, h:i A'),
            'is_overdue' => $student->next_call_followup_at?->isPast() ?? false,
            'not_connected_attempts_count' => (int) ($student->not_connected_attempts_count ?? 0),
            'profile_url' => StudentProfilePage::getUrl(['record' => $student->id]),
        ];
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
