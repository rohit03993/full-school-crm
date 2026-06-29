<?php

namespace App\Services;

use App\Models\Visit;
use App\Support\CrmPagination;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InstituteVisitsService
{
    /**
     * @return Builder<Visit>
     */
    public function baseQuery(Carbon $from, Carbon $to, string $enrollmentFilter = 'all', ?string $search = null): Builder
    {
        $query = Visit::query()
            ->with(['student.activeEnrollment', 'enquiry.course', 'staff'])
            ->whereDate('visit_date', '>=', $from->toDateString())
            ->whereDate('visit_date', '<=', $to->toDateString());

        $this->applyEnrollmentFilter($query, $enrollmentFilter);
        $this->applySearch($query, $search);

        return $query
            ->orderByDesc('visit_date')
            ->orderByDesc('id');
    }

    /**
     * @return LengthAwarePaginator<int, Visit>
     */
    public function paginate(
        Carbon $from,
        Carbon $to,
        string $enrollmentFilter = 'all',
        ?string $search = null,
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        return $this->baseQuery($from, $to, $enrollmentFilter, $search)->paginate(
            $perPage ?? CrmPagination::PER_PAGE,
            ['*'],
            'page',
            $page,
        );
    }

    /**
     * @return array{
     *     total_visits: int,
     *     unique_students: int,
     *     prospect_visits: int,
     *     enrolled_visits: int,
     *     first_time_visitors: int,
     *     repeat_visit_students: int,
     * }
     */
    public function stats(Carbon $from, Carbon $to, string $enrollmentFilter = 'all'): array
    {
        $base = $this->baseQuery($from, $to, $enrollmentFilter);

        $totalVisits = (clone $base)->count();
        $uniqueStudents = (clone $base)->distinct('student_id')->count('student_id');

        $prospectVisits = $this->applyEnrollmentFilter(
            Visit::query()
                ->whereDate('visit_date', '>=', $from->toDateString())
                ->whereDate('visit_date', '<=', $to->toDateString()),
            'prospect',
        )->count();

        $enrolledVisits = $this->applyEnrollmentFilter(
            Visit::query()
                ->whereDate('visit_date', '>=', $from->toDateString())
                ->whereDate('visit_date', '<=', $to->toDateString()),
            'enrolled',
        )->count();

        $repeatVisitStudents = $this->repeatVisitStudentCount($from, $to, $enrollmentFilter);
        $firstTimeVisitors = $this->firstTimeVisitorCount($from, $to, $enrollmentFilter);

        return [
            'total_visits' => $totalVisits,
            'unique_students' => $uniqueStudents,
            'prospect_visits' => $prospectVisits,
            'enrolled_visits' => $enrolledVisits,
            'first_time_visitors' => $firstTimeVisitors,
            'repeat_visit_students' => $repeatVisitStudents,
        ];
    }

    public function resolveDateRange(?string $from, ?string $to): array
    {
        $resolvedTo = filled($to) ? Carbon::parse($to)->startOfDay() : now()->startOfDay();
        $resolvedFrom = filled($from) ? Carbon::parse($from)->startOfDay() : $resolvedTo->copy()->startOfMonth();

        if ($resolvedFrom->gt($resolvedTo)) {
            [$resolvedFrom, $resolvedTo] = [$resolvedTo, $resolvedFrom];
        }

        return [$resolvedFrom, $resolvedTo];
    }

    /**
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    protected function applyEnrollmentFilter(Builder $query, string $enrollmentFilter): Builder
    {
        if ($enrollmentFilter === 'enrolled') {
            $query->whereHas('student', fn (Builder $studentQuery): Builder => $studentQuery->whereHas('activeEnrollment'));
        } elseif ($enrollmentFilter === 'prospect') {
            $query->whereHas('student', fn (Builder $studentQuery): Builder => $studentQuery->whereDoesntHave('activeEnrollment'));
        }

        return $query;
    }

    /**
     * @param  Builder<Visit>  $query
     */
    protected function applySearch(Builder $query, ?string $search): void
    {
        if (! filled($search)) {
            return;
        }

        $term = trim($search);

        $query->where(function (Builder $inner) use ($term): void {
            $inner->whereHas('student', function (Builder $studentQuery) use ($term): void {
                $studentQuery->where('name', 'like', '%'.$term.'%')
                    ->orWhere('mobile', 'like', '%'.preg_replace('/\D/', '', $term).'%');
            })->orWhereHas('enquiry', function (Builder $enquiryQuery) use ($term): void {
                $enquiryQuery->where('enquiry_number', 'like', '%'.strtoupper($term).'%');
            })->orWhereHas('staff', function (Builder $staffQuery) use ($term): void {
                $staffQuery->where('name', 'like', '%'.$term.'%');
            });
        });
    }

    protected function repeatVisitStudentCount(Carbon $from, Carbon $to, string $enrollmentFilter): int
    {
        $query = Visit::query()
            ->select('student_id')
            ->whereDate('visit_date', '>=', $from->toDateString())
            ->whereDate('visit_date', '<=', $to->toDateString())
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) >= 2');

        $this->applyEnrollmentFilter($query, $enrollmentFilter);

        return DB::query()
            ->fromSub($query, 'repeat_visits')
            ->count();
    }

    protected function firstTimeVisitorCount(Carbon $from, Carbon $to, string $enrollmentFilter): int
    {
        $query = Visit::query()
            ->select('student_id')
            ->whereDate('visit_date', '>=', $from->toDateString())
            ->whereDate('visit_date', '<=', $to->toDateString())
            ->whereNotIn('student_id', function ($sub) use ($from): void {
                $sub->select('student_id')
                    ->from('visits')
                    ->whereDate('visit_date', '<', $from->toDateString());
            })
            ->distinct();

        $this->applyEnrollmentFilter($query, $enrollmentFilter);

        return $query->count('student_id');
    }
}
