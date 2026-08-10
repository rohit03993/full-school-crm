<?php

namespace App\Services;

use App\Enums\EnrolledCallPurpose;
use App\Enums\RoleName;
use App\Models\StudentCall;
use App\Models\User;
use App\Support\CrmPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StudentCallsService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     from: string,
     *     to: string,
     *     purpose: ?string,
     *     search: string,
     *     staff_user_id: ?int
     * }
     */
    public function normalizeFilters(array $input): array
    {
        $from = filled($input['from'] ?? null)
            ? Carbon::parse($input['from'])->toDateString()
            : today()->subDays(29)->toDateString();

        $to = filled($input['to'] ?? null)
            ? Carbon::parse($input['to'])->toDateString()
            : today()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $purpose = filled($input['purpose'] ?? null) ? (string) $input['purpose'] : null;
        if ($purpose !== null && EnrolledCallPurpose::tryFrom($purpose) === null) {
            $purpose = null;
        }

        return [
            'from' => $from,
            'to' => $to,
            'purpose' => $purpose,
            'search' => trim((string) ($input['search'] ?? '')),
            'staff_user_id' => filled($input['staff_user_id'] ?? null)
                ? (int) $input['staff_user_id']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: int}
     */
    public function summary(array $filters): array
    {
        return [
            'total' => $this->filteredQuery($filters)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, ?int $perPage = null, ?int $page = null): LengthAwarePaginator
    {
        $query = $this->filteredQuery($filters)
            ->with(['student.activeEnrollment', 'staff'])
            ->orderByDesc('called_at');

        $perPage = $perPage ?? CrmPagination::PER_PAGE;

        return $page !== null
            ? $query->paginate($perPage, ['*'], 'page', $page)
            : $query->paginate($perPage);
    }

    /**
     * @return array<string, string>
     */
    public function purposeOptions(): array
    {
        return EnrolledCallPurpose::options();
    }

    /**
     * @return array<int, string>
     */
    public function staffOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                RoleName::SuperAdmin->value,
                RoleName::Staff->value,
            ]))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Enrolled service calls only (purpose logged from student profile).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<StudentCall>
     */
    protected function filteredQuery(array $filters): Builder
    {
        $query = StudentCall::query()
            ->whereNotNull('call_purpose')
            ->whereDate('called_at', '>=', $filters['from'])
            ->whereDate('called_at', '<=', $filters['to']);

        if (filled($filters['purpose'] ?? null)) {
            $query->where('call_purpose', $filters['purpose']);
        }

        if (filled($filters['staff_user_id'] ?? null)) {
            $query->where('user_id', (int) $filters['staff_user_id']);
        }

        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->whereHas('student', function (Builder $student) use ($search): void {
                $student->where('name', 'like', '%'.$search.'%')
                    ->orWhere('mobile', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }
}
