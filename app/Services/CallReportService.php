<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\StudentCall;
use App\Models\User;
use App\Support\CrmPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CallReportService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     from: string,
     *     to: string,
     *     connection: string,
     *     call_status: ?string,
     *     visit_status: ?string,
     *     call_type: string,
     *     search: string,
     *     staff_user_id: ?int
     * }
     */
    public function normalizeFilters(array $input, User $viewer): array
    {
        $from = filled($input['from'] ?? null)
            ? Carbon::parse($input['from'])->toDateString()
            : today()->subDays(6)->toDateString();

        $to = filled($input['to'] ?? null)
            ? Carbon::parse($input['to'])->toDateString()
            : today()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $staffUserId = null;
        if ($this->canViewAllStaff($viewer)) {
            $staffUserId = filled($input['staff_user_id'] ?? null)
                ? (int) $input['staff_user_id']
                : null;
        }

        return [
            'from' => $from,
            'to' => $to,
            'connection' => in_array($input['connection'] ?? 'all', ['all', 'connected', 'not_connected'], true)
                ? ($input['connection'] ?? 'all')
                : 'all',
            'call_status' => filled($input['call_status'] ?? null) ? (string) $input['call_status'] : null,
            'visit_status' => filled($input['visit_status'] ?? null) ? (string) $input['visit_status'] : null,
            'call_type' => in_array($input['call_type'] ?? 'all', ['all', 'new', 'followup'], true)
                ? ($input['call_type'] ?? 'all')
                : 'all',
            'search' => trim((string) ($input['search'] ?? '')),
            'staff_user_id' => $staffUserId,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     total: int,
     *     connected: int,
     *     not_connected: int,
     *     new_calls: int,
     *     followup_calls: int
     * }
     */
    public function summary(array $filters, User $viewer): array
    {
        $base = $this->filteredQuery($filters, $viewer);
        $total = (clone $base)->count();
        $connected = (clone $base)->where('call_status', CallStatus::Connected)->count();
        $notConnected = (clone $base)->whereIn('call_status', CallStatus::notConnectedValues())->count();

        $breakdown = $this->newVsFollowUpBreakdown($filters, $viewer);

        return [
            'total' => $total,
            'connected' => $connected,
            'not_connected' => $notConnected,
            'new_calls' => $breakdown['new_calls'],
            'followup_calls' => $breakdown['followup_calls'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function calls(array $filters, User $viewer, ?int $perPage = null, ?int $page = null): LengthAwarePaginator
    {
        $query = $this->filteredQuery($filters, $viewer)
            ->with(['student', 'staff', 'enquiry.course'])
            ->orderByDesc('called_at');

        $perPage = $perPage ?? CrmPagination::PER_PAGE;

        return $page !== null
            ? $query->paginate($perPage, ['*'], 'page', $page)
            : $query->paginate($perPage);
    }

    /**
     * @return Collection<int, User>
     */
    public function staffOptions(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                RoleName::SuperAdmin->value,
                RoleName::Staff->value,
            ]))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function canViewAllStaff(User $viewer): bool
    {
        return $viewer->hasRole(RoleName::SuperAdmin->value);
    }

    public function isNewCall(StudentCall $call): bool
    {
        $firstId = StudentCall::query()
            ->where('student_id', $call->student_id)
            ->min('id');

        return $firstId !== null && (int) $firstId === (int) $call->id;
    }

    /**
     * @param  iterable<int, StudentCall>  $calls
     * @return array<int, int> student_id => first_call_id
     */
    public function firstCallIdsFor(iterable $calls): array
    {
        $studentIds = collect($calls)->pluck('student_id')->unique()->filter()->values();

        if ($studentIds->isEmpty()) {
            return [];
        }

        return StudentCall::query()
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, MIN(id) as first_call_id')
            ->groupBy('student_id')
            ->pluck('first_call_id', 'student_id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<StudentCall>
     */
    protected function filteredQuery(array $filters, User $viewer): Builder
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();

        $query = StudentCall::query()
            ->whereBetween('called_at', [$from, $to]);

        if ($this->canViewAllStaff($viewer)) {
            if ($filters['staff_user_id']) {
                $query->where('user_id', $filters['staff_user_id']);
            }
        } else {
            $query->where('user_id', $viewer->id);
        }

        $connection = $filters['connection'] ?? 'all';
        if ($connection === 'connected') {
            $query->where('call_status', CallStatus::Connected);
        } elseif ($connection === 'not_connected') {
            $query->whereIn('call_status', CallStatus::notConnectedValues());
        }

        if ($filters['call_status']) {
            $query->where('call_status', $filters['call_status']);
        }

        if ($filters['visit_status']) {
            $query->where('visit_status_changed_to', $filters['visit_status']);
        }

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $digits = preg_replace('/\D/', '', $term);

            $query->whereHas('student', function (Builder $studentQuery) use ($term, $digits): void {
                $studentQuery->where('name', 'like', '%'.$term.'%')
                    ->orWhere('father_name', 'like', '%'.$term.'%');

                if (filled($digits)) {
                    $studentQuery->orWhere('mobile', 'like', '%'.$digits.'%');
                }
            });
        }

        $this->applyCallTypeFilter($query, $filters['call_type'] ?? 'all');

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{new_calls: int, followup_calls: int}
     */
    protected function newVsFollowUpBreakdown(array $filters, User $viewer): array
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $table = (new StudentCall)->getTable();

        $firstCallSub = DB::table($table)
            ->select('student_id', DB::raw('MIN(id) as first_call_id'))
            ->groupBy('student_id');

        $query = DB::table($table.' as sc')
            ->joinSub($firstCallSub, 'fc', fn ($join) => $join->on('fc.student_id', '=', 'sc.student_id'))
            ->whereBetween('sc.called_at', [$from, $to]);

        if ($this->canViewAllStaff($viewer)) {
            if ($filters['staff_user_id']) {
                $query->where('sc.user_id', $filters['staff_user_id']);
            }
        } else {
            $query->where('sc.user_id', $viewer->id);
        }

        $connection = $filters['connection'] ?? 'all';
        if ($connection === 'connected') {
            $query->where('sc.call_status', CallStatus::Connected->value);
        } elseif ($connection === 'not_connected') {
            $query->whereIn('sc.call_status', CallStatus::notConnectedValues());
        }

        if ($filters['call_status']) {
            $query->where('sc.call_status', $filters['call_status']);
        }

        if ($filters['visit_status']) {
            $query->where('sc.visit_status_changed_to', $filters['visit_status']);
        }

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $digits = preg_replace('/\D/', '', $term);

            $query->join('students as st', 'st.id', '=', 'sc.student_id')
                ->where(function (QueryBuilder $inner) use ($term, $digits): void {
                    $inner->where('st.name', 'like', '%'.$term.'%')
                        ->orWhere('st.father_name', 'like', '%'.$term.'%');

                    if (filled($digits)) {
                        $inner->orWhere('st.mobile', 'like', '%'.$digits.'%');
                    }
                });
        }

        $callType = $filters['call_type'] ?? 'all';
        if ($callType === 'new') {
            $query->whereColumn('sc.id', 'fc.first_call_id');
        } elseif ($callType === 'followup') {
            $query->whereColumn('sc.id', '<>', 'fc.first_call_id');
        }

        $row = $query
            ->selectRaw('SUM(CASE WHEN sc.id = fc.first_call_id THEN 1 ELSE 0 END) as new_calls')
            ->selectRaw('SUM(CASE WHEN sc.id <> fc.first_call_id THEN 1 ELSE 0 END) as followup_calls')
            ->first();

        return [
            'new_calls' => (int) ($row->new_calls ?? 0),
            'followup_calls' => (int) ($row->followup_calls ?? 0),
        ];
    }

    protected function applyCallTypeFilter(Builder $query, string $callType): void
    {
        if ($callType === 'all') {
            return;
        }

        $table = $query->getModel()->getTable();

        if ($callType === 'new') {
            $query->whereRaw("{$table}.id = (SELECT MIN(sc2.id) FROM {$table} sc2 WHERE sc2.student_id = {$table}.student_id)");
        } else {
            $query->whereRaw("{$table}.id <> (SELECT MIN(sc2.id) FROM {$table} sc2 WHERE sc2.student_id = {$table}.student_id)");
        }
    }
}
