<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentConductRecord;
use App\Models\User;
use App\Support\ConductTypeOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentConductService
{
    public function canRecord(?User $user): bool
    {
        if (! $user?->is_active) {
            return false;
        }

        return $user->hasRole(RoleName::SuperAdmin->value)
            || $user->hasRole(StaffJobRole::AcademicCoordinator->value)
            || $user->hasRole(StaffJobRole::Teacher->value);
    }

    public function canRecordStudent(?User $user, Student $student): bool
    {
        return $this->recordDenial($user, $student) === null;
    }

    public function recordDenial(?User $user, Student $student): ?string
    {
        if (! $user?->is_active) {
            return 'You cannot add a report for this student.';
        }

        if ($student->status === StudentStatus::Dropped) {
            return 'This student is inactive. New reports stay locked until they are made active.';
        }

        $student->loadMissing(['activeEnrollment', 'activeBatchStudent']);

        if ($student->activeEnrollment === null) {
            return 'Conduct records are only for enrolled students.';
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)
            || $user->hasRole(StaffJobRole::AcademicCoordinator->value)) {
            return null;
        }

        if ($user->hasRole(StaffJobRole::Teacher->value)) {
            $batchId = (int) ($student->activeBatchStudent?->batch_id ?? 0);

            if ($batchId > 0 && app(BatchStaffAssignmentService::class)->canAccessClass($user, $batchId)) {
                return null;
            }

            return 'You can report only students in a class assigned to you.';
        }

        return 'You cannot add a report for this student.';
    }

    public function canDelete(?User $user): bool
    {
        return (bool) $user?->is_active
            && $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function countForStudent(Student $student): int
    {
        return $student->conductRecords()->count();
    }

    /**
     * @return array{total: int, with_fine: int, without_fine: int, fine_amount: int}
     */
    public function stats(?User $viewer = null): array
    {
        $base = $this->visibleTo($viewer);

        return [
            'total' => (clone $base)->count(),
            'with_fine' => (clone $base)->where('fine_charged', true)->count(),
            'without_fine' => (clone $base)->where('fine_charged', false)->count(),
            'fine_amount' => (int) (clone $base)->where('fine_charged', true)->sum('amount'),
        ];
    }

    public function paginate(string $search = '', string $fine = '', string $type = '', int $perPage = 15, ?User $viewer = null): LengthAwarePaginator
    {
        $query = $this->visibleTo($viewer)
            ->with(['student:id,name', 'recordedBy:id,name'])
            ->orderByDesc('recorded_on')
            ->orderByDesc('id');

        if ($fine === 'fine') {
            $query->where('fine_charged', true);
        } elseif ($fine === 'without') {
            $query->where('fine_charged', false);
        }

        if ($type !== '') {
            $query->whereJsonContains('type_keys', $type);
        }

        $search = trim($search);

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('note', 'like', '%'.$search.'%')
                    ->orWhereHas('student', fn ($student) => $student->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('recordedBy', fn ($staff) => $staff->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentsInClass(User $user, int $batchId): Collection
    {
        if ($batchId < 1 || ! app(BatchStaffAssignmentService::class)->canAccessClass($user, $batchId)) {
            return collect();
        }

        return Student::query()
            ->where('status', StudentStatus::Enrolled->value)
            ->whereHas('activeEnrollment')
            ->whereHas(
                'batchStudents',
                fn (Builder $batchStudents): Builder => $batchStudents
                    ->where('batch_id', $batchId)
                    ->where('is_active', true),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'mobile']);
    }

    /**
     * @param  array{
     *     recorded_on?: mixed,
     *     type_keys?: mixed,
     *     fine_choice?: mixed,
     *     amount?: mixed,
     *     note?: mixed
     * }  $input
     */
    public function store(Student $student, User $staff, array $input): StudentConductRecord
    {
        $denial = $this->recordDenial($staff, $student);

        if ($denial !== null) {
            throw ValidationException::withMessages([
                'conduct' => $denial,
            ]);
        }

        return $this->createRecord($student, $staff, $this->validatedInput($input));
    }

    /**
     * @param  list<int|string>  $studentIds
     * @param  array{
     *     recorded_on?: mixed,
     *     type_keys?: mixed,
     *     fine_choice?: mixed,
     *     amount?: mixed,
     *     note?: mixed
     * }  $input
     * @return Collection<int, StudentConductRecord>
     */
    public function storeMany(User $staff, array $studentIds, array $input): Collection
    {
        $ids = collect($studentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'students' => 'Select at least one student.',
            ]);
        }

        $validated = $this->validatedInput($input);

        $students = Student::query()
            ->with(['activeEnrollment', 'activeBatchStudent'])
            ->whereIn('id', $ids)
            ->get();

        if ($students->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'students' => 'One of the selected students was not found.',
            ]);
        }

        foreach ($students as $student) {
            $denial = $this->recordDenial($staff, $student);

            if ($denial !== null) {
                throw ValidationException::withMessages([
                    'students' => $student->name.': '.$denial,
                ]);
            }
        }

        return $students
            ->map(fn (Student $student): StudentConductRecord => $this->createRecord($student, $staff, $validated))
            ->values();
    }

    /**
     * @param  array{
     *     recorded_on?: mixed,
     *     type_keys?: mixed,
     *     fine_choice?: mixed,
     *     amount?: mixed,
     *     note?: mixed
     * }  $input
     * @return array{recorded_on: mixed, type_keys: list<string>, fine_charged: bool, amount: ?int, note: string}
     */
    protected function validatedInput(array $input): array
    {
        $fineCharged = ($input['fine_choice'] ?? 'without_fine') === 'fine_charged';
        $types = array_values(array_unique(array_filter(
            (array) ($input['type_keys'] ?? []),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        )));

        $validated = Validator::make(
            [
                'recorded_on' => $input['recorded_on'] ?? null,
                'type_keys' => $types,
                'note' => trim((string) ($input['note'] ?? '')),
                'amount' => $fineCharged ? trim((string) ($input['amount'] ?? '')) : null,
            ],
            [
                'recorded_on' => ['required', 'date', 'before_or_equal:today'],
                'type_keys' => ['required', 'array', 'min:1'],
                'type_keys.*' => ['string', Rule::in(array_keys(ConductTypeOptions::formOptions()))],
                'note' => ['required', 'string', 'max:1000'],
                'amount' => $fineCharged
                    ? ['required', 'integer', 'min:1', 'max:9999999']
                    : ['nullable'],
            ],
            [
                'recorded_on.required' => 'Choose the date.',
                'recorded_on.before_or_equal' => 'The date cannot be in the future.',
                'type_keys.required' => 'Select at least one type.',
                'type_keys.min' => 'Select at least one type.',
                'type_keys.*.in' => 'Choose types from the list.',
                'note.required' => 'Write what happened.',
                'amount.required' => 'Enter the fine amount.',
                'amount.integer' => 'Enter the fine amount in figures.',
                'amount.min' => 'Enter the fine amount in figures.',
            ],
        )->validate();

        return [
            'recorded_on' => $validated['recorded_on'],
            'type_keys' => $validated['type_keys'],
            'fine_charged' => $fineCharged,
            'amount' => $fineCharged ? (int) $validated['amount'] : null,
            'note' => $validated['note'],
        ];
    }

    /**
     * @param  array{recorded_on: mixed, type_keys: list<string>, fine_charged: bool, amount: ?int, note: string}  $validated
     */
    protected function createRecord(Student $student, User $staff, array $validated): StudentConductRecord
    {
        return StudentConductRecord::query()->create([
            'student_id' => $student->id,
            'recorded_on' => $validated['recorded_on'],
            'type_keys' => $validated['type_keys'],
            'fine_charged' => $validated['fine_charged'],
            'amount' => $validated['amount'],
            'note' => $validated['note'],
            'recorded_by_user_id' => $staff->id,
        ]);
    }

    protected function visibleTo(?User $viewer): Builder
    {
        $query = StudentConductRecord::query();

        if (! $viewer || $this->seesEveryReport($viewer)) {
            return $query;
        }

        if ($viewer->hasRole(StaffJobRole::Teacher->value)) {
            return $query->where('recorded_by_user_id', $viewer->id);
        }

        return $query->whereRaw('0 = 1');
    }

    protected function seesEveryReport(User $viewer): bool
    {
        return $viewer->hasRole(RoleName::SuperAdmin->value)
            || $viewer->hasRole(StaffJobRole::AcademicCoordinator->value);
    }

    public function delete(StudentConductRecord $record, User $staff): void
    {
        if (! $this->canDelete($staff)) {
            throw ValidationException::withMessages([
                'conduct' => 'Only Super Admin can remove a conduct record.',
            ]);
        }

        $record->delete();
    }
}
