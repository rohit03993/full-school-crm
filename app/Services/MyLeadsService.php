<?php

namespace App\Services;

use App\Enums\LeadSource;
use App\Enums\VisitStatus;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class MyLeadsService
{
    protected const STATS_CACHE_SECONDS = 60;

    /**
     * Admin-assigned calling list only — every explicit assignment counts.
     *
     * @return Builder<Enquiry>
     */
    public function countBaseQuery(User $staff): Builder
    {
        return Enquiry::query()
            ->where('meeting_with_user_id', $staff->id)
            ->whereNotNull('calling_assigned_at');
    }

    /**
     * @return Builder<Enquiry>
     */
    public function baseQuery(User $staff): Builder
    {
        return $this->countBaseQuery($staff)
            ->with(['student.lastCall.staff', 'course', 'meetingWith', 'callingAssignedBy']);
    }

    /**
     * @return LengthAwarePaginator<int, Enquiry>
     */
    public function paginateLeads(
        User $staff,
        ?string $search = null,
        ?string $calledFilter = 'all',
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        $query = $this->applyLeadFilters($this->baseQuery($staff), $search, $calledFilter);

        return $query
            ->orderByDesc('calling_assigned_at')
            ->orderByDesc('created_at')
            ->paginate(
                $perPage ?? CrmPagination::PER_PAGE,
                ['*'],
                'page',
                $page,
            );
    }

    /**
     * @return Builder<Enquiry>
     */
    protected function applyLeadFilters(Builder $query, ?string $search, ?string $calledFilter): Builder
    {
        if (filled($search)) {
            $term = trim($search);
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('enquiry_number', 'like', '%'.strtoupper($term).'%')
                    ->orWhereHas('student', function (Builder $studentQuery) use ($term): void {
                        $studentQuery->where('name', 'like', '%'.$term.'%')
                            ->orWhere('mobile', 'like', '%'.preg_replace('/\D/', '', $term).'%')
                            ->orWhere('father_name', 'like', '%'.$term.'%');
                    });
            });
        }

        if ($calledFilter === 'uncalled') {
            $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('total_calls', 0));
        } elseif ($calledFilter === 'called') {
            $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('total_calls', '>', 0));
        }

        return $query;
    }

    /**
     * @return Collection<int, Enquiry>
     */
    public function leads(User $staff, ?string $search = null, ?string $calledFilter = 'all'): Collection
    {
        return $this->applyLeadFilters($this->baseQuery($staff), $search, $calledFilter)
            ->orderByDesc('calling_assigned_at')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();
    }

    /**
     * @return array{total: int, uncalled: int, called: int, due_call_followups: int}
     */
    public function stats(User $staff): array
    {
        return Cache::remember(
            $this->statsCacheKey($staff->id),
            self::STATS_CACHE_SECONDS,
            fn (): array => $this->computeStats($staff),
        );
    }

    /**
     * @return array{total: int, uncalled: int, called: int, due_call_followups: int}
     */
    protected function computeStats(User $staff): array
    {
        $base = $this->countBaseQuery($staff);

        return [
            'total' => (clone $base)->count(),
            'uncalled' => (clone $base)->whereHas('student', fn (Builder $q) => $q->where('total_calls', 0))->count(),
            'called' => (clone $base)->whereHas('student', fn (Builder $q) => $q->where('total_calls', '>', 0))->count(),
            'due_call_followups' => (clone $base)->whereHas('student', function (Builder $q): void {
                $q->whereNotNull('next_call_followup_at')
                    ->where('next_call_followup_at', '<=', now())
                    ->where('is_call_blocked', false);
            })->count(),
        ];
    }

    public static function flushStatsCache(?int $staffUserId = null): void
    {
        if ($staffUserId !== null) {
            Cache::forget((new self)->statsCacheKey($staffUserId));

            return;
        }
    }

    protected function statsCacheKey(int $staffUserId): string
    {
        return "crm.my_leads.stats.{$staffUserId}";
    }
}
