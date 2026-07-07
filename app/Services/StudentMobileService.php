<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StudentMobileService
{
    public function normalize(?string $mobile, string $field = 'mobile'): ?string
    {
        if (blank($mobile)) {
            return null;
        }

        $normalized = preg_replace('/\D/', '', (string) $mobile);

        if (! preg_match('/^[6-9]\d{9}$/', $normalized)) {
            throw ValidationException::withMessages([
                $field => 'Please enter a valid 10-digit Indian mobile number.',
            ]);
        }

        return $normalized;
    }

    public function validateForUpdate(
        Student $student,
        string $mobile,
        ?string $alternateMobile = null,
        ?User $actor = null,
    ): array {
        $mobile = $this->normalize($mobile, 'mobile') ?? '';
        $alternateMobile = filled($alternateMobile)
            ? $this->normalize($alternateMobile, 'alternate_mobile')
            : null;
        $relaxUniqueness = $this->actorMayRelaxUniqueness($actor);

        if ($mobile === '') {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile number is required.',
            ]);
        }

        if ($alternateMobile !== null && $alternateMobile === $mobile) {
            throw ValidationException::withMessages([
                'alternate_mobile' => 'Alternate mobile must be different from primary mobile.',
            ]);
        }

        if (! $relaxUniqueness && $this->numberUsedByOtherStudent($mobile, $student->id)) {
            throw ValidationException::withMessages([
                'mobile' => 'This mobile is already registered to another student.',
            ]);
        }

        if ($alternateMobile !== null && ! $relaxUniqueness && $this->numberUsedByOtherStudent($alternateMobile, $student->id)) {
            throw ValidationException::withMessages([
                'alternate_mobile' => 'This number is already used by another student.',
            ]);
        }

        return [
            'mobile' => $mobile,
            'alternate_mobile' => $alternateMobile,
        ];
    }

    public function findStudentByNumber(string $mobile, bool $restoreIfTrashed = false): ?Student
    {
        $mobile = $this->normalize($mobile, 'mobile') ?? '';

        if ($mobile === '') {
            return null;
        }

        $student = Student::query()
            ->where(function ($query) use ($mobile): void {
                $query->where('mobile', $mobile)
                    ->orWhere('alternate_mobile', $mobile);
            })
            ->first();

        if ($student !== null || ! $restoreIfTrashed) {
            return $student;
        }

        $trashed = Student::onlyTrashed()
            ->where(function ($query) use ($mobile): void {
                $query->where('mobile', $mobile)
                    ->orWhere('alternate_mobile', $mobile);
            })
            ->first();

        if ($trashed !== null) {
            $trashed->restore();
        }

        return $trashed;
    }

    protected function numberUsedByOtherStudent(string $number, int $exceptStudentId): bool
    {
        return Student::withTrashed()
            ->where('id', '!=', $exceptStudentId)
            ->where(function ($query) use ($number): void {
                $query->where('mobile', $number)
                    ->orWhere('alternate_mobile', $number);
            })
            ->exists();
    }

    protected function actorMayRelaxUniqueness(?User $actor): bool
    {
        return $actor?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }
}
