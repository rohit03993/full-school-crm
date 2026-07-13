<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\VisitMeetingAssignmentStatus;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Support\CrmAccess;
use App\Support\CrmPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FollowUpWorklistService
{
    protected const DUE_COUNT_CACHE_SECONDS = 60;

    public const LIST_LIMIT = CrmPagination::PER_PAGE;

    public function canViewAllFollowUps(User $viewer): bool
    {
        return CrmAccess::can($viewer, CrmPermission::LeadsViewAll);
    }

    /**
     * @return EloquentCollection<int, Visit>
     */
    public function dueAndOverdue(User $viewer, int $limit = self::LIST_LIMIT): EloquentCollection
    {
        return $this->visitBaseQuery($viewer)
            ->whereDate('next_follow_up_date', '<=', today())
            ->orderBy('next_follow_up_date')
            ->limit($limit)
            ->get();
    }

    public function dueAndOverdueCount(User $viewer): int
    {
        return $this->visitCountQuery($viewer)
            ->whereDate('next_follow_up_date', '<=', today())
            ->count();
    }

    /**
     * @return EloquentCollection<int, Visit>
     */
    public function upcoming(User $viewer, int $days = 7, int $limit = self::LIST_LIMIT): EloquentCollection
    {
        $until = today()->addDays($days);

        return $this->visitBaseQuery($viewer)
            ->whereDate('next_follow_up_date', '>', today())
            ->whereDate('next_follow_up_date', '<=', $until)
            ->orderBy('next_follow_up_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Student>
     */
    public function dueCallFollowUps(User $viewer, int $limit = self::LIST_LIMIT): EloquentCollection
    {
        return $this->callFollowUpBaseQuery($viewer)
            ->whereDate('next_call_followup_at', '<=', today())
            ->orderBy('next_call_followup_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Student>
     */
    public function upcomingCallFollowUps(User $viewer, int $days = 7, int $limit = self::LIST_LIMIT): EloquentCollection
    {
        $until = today()->addDays($days);

        return $this->callFollowUpBaseQuery($viewer)
            ->whereDate('next_call_followup_at', '>', today())
            ->whereDate('next_call_followup_at', '<=', $until)
            ->orderBy('next_call_followup_at')
            ->limit($limit)
            ->get();
    }

    public function upcomingVisitsCount(User $viewer, int $days = 7): int
    {
        $until = today()->addDays($days);

        return $this->visitCountQuery($viewer)
            ->whereDate('next_follow_up_date', '>', today())
            ->whereDate('next_follow_up_date', '<=', $until)
            ->count();
    }

    public function upcomingCallFollowUpsCount(User $viewer, int $days = 7): int
    {
        $until = today()->addDays($days);

        return $this->callFollowUpCountQuery($viewer)
            ->whereDate('next_call_followup_at', '>', today())
            ->whereDate('next_call_followup_at', '<=', $until)
            ->count();
    }

    public function dueCount(User $viewer): int
    {
        return $this->visitCountQuery($viewer)
            ->whereDate('next_follow_up_date', '<=', today())
            ->count();
    }

    public function dueCallFollowUpCount(User $viewer): int
    {
        return $this->callFollowUpCountQuery($viewer)
            ->whereDate('next_call_followup_at', '<=', today())
            ->count();
    }

    public function totalDueCount(User $viewer): int
    {
        $cacheKey = $this->dueCountCacheKey($viewer);

        return Cache::remember($cacheKey, self::DUE_COUNT_CACHE_SECONDS, function () use ($viewer): int {
            return $this->dueCount($viewer) + $this->dueCallFollowUpCount($viewer);
        });
    }

    public static function flushDueCountCache(): void
    {
        Cache::increment('crm.followups.cache_version');
    }

    public function callFollowUpAssigneeName(Student $student): ?string
    {
        $enquiry = $student->enquiries->first();

        if ($enquiry?->calling_assigned_at && $enquiry->meetingWith) {
            return $enquiry->meetingWith->name;
        }

        return $student->lastCall?->staff?->name;
    }

    /**
     * @return Builder<Visit>
     */
    protected function visitCountQuery(User $viewer): Builder
    {
        $query = Visit::query()
            ->inPerson()
            ->whereNotNull('next_follow_up_date');

        return $this->scopeVisitsForViewer($query, $viewer);
    }

    /**
     * @return Builder<Visit>
     */
    protected function visitBaseQuery(User $viewer): Builder
    {
        return $this->visitCountQuery($viewer)
            ->with(['student', 'enquiry.course', 'staff', 'enquiry.meetingWith']);
    }

    /**
     * @return Builder<Student>
     */
    protected function callFollowUpCountQuery(User $viewer): Builder
    {
        $query = Student::query()
            ->where('is_call_blocked', false)
            ->whereNotNull('next_call_followup_at')
            ->whereDoesntHave('activeEnrollment');

        return $this->scopeCallFollowUpsForViewer($query, $viewer);
    }

    /**
     * @return Builder<Student>
     */
    protected function callFollowUpBaseQuery(User $viewer): Builder
    {
        return $this->callFollowUpCountQuery($viewer)
            ->with([
                'lastCall.staff',
                'enquiries' => fn ($query) => $query->latest()->limit(1),
                'enquiries.course',
                'enquiries.meetingWith',
            ]);
    }

    /**
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    protected function scopeVisitsForViewer(Builder $query, User $viewer): Builder
    {
        if ($this->canViewAllFollowUps($viewer)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($viewer): void {
            $inner->where('staff_user_id', $viewer->id)
                ->orWhereHas('enquiry', fn (Builder $enquiryQuery) => $enquiryQuery
                    ->where('meeting_with_user_id', $viewer->id))
                ->orWhereHas('student.visitMeetingAssignments', fn (Builder $assignmentQuery) => $assignmentQuery
                    ->where('assigned_to_user_id', $viewer->id)
                    ->where('status', VisitMeetingAssignmentStatus::Open));
        });
    }

    /**
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    protected function scopeCallFollowUpsForViewer(Builder $query, User $viewer): Builder
    {
        if ($this->canViewAllFollowUps($viewer)) {
            return $query;
        }

        return $query->whereHas('enquiries', function (Builder $enquiryQuery) use ($viewer): void {
            $enquiryQuery->where('meeting_with_user_id', $viewer->id)
                ->whereNotNull('calling_assigned_at');
        });
    }

    protected function dueCountCacheKey(User $viewer): string
    {
        $scope = $this->canViewAllFollowUps($viewer) ? 'all' : 'staff.'.$viewer->id;
        $version = (int) Cache::get('crm.followups.cache_version', 1);

        return "crm.followups.total_due.v{$version}.{$scope}";
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
