<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Visit;
use App\Support\CrmPagination;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FollowUpWorklistService
{
    protected const DUE_COUNT_CACHE_SECONDS = 60;

    public const LIST_LIMIT = CrmPagination::PER_PAGE;
    /**
     * @return EloquentCollection<int, Visit>
     */
    public function dueAndOverdue(int $limit = self::LIST_LIMIT): EloquentCollection
    {
        return $this->visitBaseQuery()
            ->whereDate('next_follow_up_date', '<=', today())
            ->orderBy('next_follow_up_date')
            ->limit($limit)
            ->get();
    }

    public function dueAndOverdueCount(): int
    {
        return $this->visitCountQuery()
            ->whereDate('next_follow_up_date', '<=', today())
            ->count();
    }

    /**
     * @return EloquentCollection<int, Visit>
     */
    public function upcoming(int $days = 7, int $limit = self::LIST_LIMIT): EloquentCollection
    {
        $until = today()->addDays($days);

        return $this->visitBaseQuery()
            ->whereDate('next_follow_up_date', '>', today())
            ->whereDate('next_follow_up_date', '<=', $until)
            ->orderBy('next_follow_up_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Student>
     */
    public function dueCallFollowUps(int $limit = self::LIST_LIMIT): EloquentCollection
    {
        return $this->callFollowUpBaseQuery()
            ->whereDate('next_call_followup_at', '<=', today())
            ->orderBy('next_call_followup_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Student>
     */
    public function upcomingCallFollowUps(int $days = 7, int $limit = self::LIST_LIMIT): EloquentCollection
    {
        $until = today()->addDays($days);

        return $this->callFollowUpBaseQuery()
            ->whereDate('next_call_followup_at', '>', today())
            ->whereDate('next_call_followup_at', '<=', $until)
            ->orderBy('next_call_followup_at')
            ->limit($limit)
            ->get();
    }

    public function upcomingVisitsCount(int $days = 7): int
    {
        $until = today()->addDays($days);

        return $this->visitCountQuery()
            ->whereDate('next_follow_up_date', '>', today())
            ->whereDate('next_follow_up_date', '<=', $until)
            ->count();
    }

    public function upcomingCallFollowUpsCount(int $days = 7): int
    {
        $until = today()->addDays($days);

        return $this->callFollowUpCountQuery()
            ->whereDate('next_call_followup_at', '>', today())
            ->whereDate('next_call_followup_at', '<=', $until)
            ->count();
    }

    public function dueCount(): int
    {
        return $this->visitCountQuery()
            ->whereDate('next_follow_up_date', '<=', today())
            ->count();
    }

    public function dueCallFollowUpCount(): int
    {
        return $this->callFollowUpCountQuery()
            ->whereDate('next_call_followup_at', '<=', today())
            ->count();
    }

    public function totalDueCount(): int
    {
        return Cache::remember('crm.followups.total_due', self::DUE_COUNT_CACHE_SECONDS, function (): int {
            return $this->dueCount() + $this->dueCallFollowUpCount();
        });
    }

    public static function flushDueCountCache(): void
    {
        Cache::forget('crm.followups.total_due');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Visit>
     */
    protected function visitCountQuery()
    {
        return Visit::query()->whereNotNull('next_follow_up_date');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Visit>
     */
    protected function visitBaseQuery()
    {
        return $this->visitCountQuery()
            ->with(['student', 'enquiry.course', 'staff']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Student>
     */
    protected function callFollowUpCountQuery()
    {
        return Student::query()
            ->where('is_call_blocked', false)
            ->whereNotNull('next_call_followup_at')
            ->whereDoesntHave('activeEnrollment');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Student>
     */
    protected function callFollowUpBaseQuery()
    {
        return $this->callFollowUpCountQuery()
            ->with([
                'lastCall.staff',
                'enquiries' => fn ($query) => $query->latest()->limit(1),
                'enquiries.course',
            ]);
    }

    public function followUpStatusLabel(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'Due today';
        }

        if ($date->isPast()) {
            $days = (int) $date->diffInDays(today());

            return $days === 1 ? '1 day overdue' : "{$days} days overdue";
        }

        $days = (int) today()->diffInDays($date);

        return $days === 1 ? 'Due tomorrow' : "Due in {$days} days";
    }
}
