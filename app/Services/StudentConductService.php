<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\Student;
use App\Models\StudentConductRecord;
use App\Models\User;
use App\Support\ConductTypeOptions;
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
            || $user->hasRole(StaffJobRole::AcademicCoordinator->value);
    }

    public function canDelete(?User $user): bool
    {
        return (bool) $user?->is_active
            && $user->hasRole(RoleName::SuperAdmin->value);
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
        if (! $this->canRecord($staff)) {
            throw ValidationException::withMessages([
                'conduct' => 'You cannot add a conduct record.',
            ]);
        }

        $student->loadMissing('activeEnrollment');

        if ($student->activeEnrollment === null) {
            throw ValidationException::withMessages([
                'conduct' => 'Conduct records are only for enrolled students.',
            ]);
        }

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

        return StudentConductRecord::query()->create([
            'student_id' => $student->id,
            'recorded_on' => $validated['recorded_on'],
            'type_keys' => $validated['type_keys'],
            'fine_charged' => $fineCharged,
            'amount' => $fineCharged ? (int) $validated['amount'] : null,
            'note' => $validated['note'],
            'recorded_by_user_id' => $staff->id,
        ]);
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
