<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StudentUpdateService
{
    public function __construct(
        protected StudentAuthService $studentAuth,
        protected AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data, ?User $staff = null): Student
    {
        return DB::transaction(function () use ($student, $data, $staff): Student {
            $oldValues = $student->only([
                'name',
                'father_name',
                'date_of_birth',
                'gender',
                'alternate_mobile',
                'email',
                'address',
                'city',
                'state',
                'pincode',
                'category',
            ]);

            $attributes = [
                'name' => $data['name'],
                'father_name' => $data['father_name'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'alternate_mobile' => $data['alternate_mobile'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'pincode' => $data['pincode'] ?? null,
                'category' => $data['category'] ?? $student->category?->value ?? 'general',
            ];

            if (filled($data['date_of_birth'] ?? null)) {
                $attributes['portal_password'] = $this->studentAuth->hashPortalPassword(
                    $this->studentAuth->formatDobPassword(
                        \Carbon\Carbon::parse($data['date_of_birth']),
                    ),
                );
            }

            $student->update($attributes);

            $this->audit->log(
                action: 'Student Profile Updated',
                auditable: $student,
                oldValues: $oldValues,
                newValues: $student->only(array_keys($oldValues)),
                user: $staff,
            );

            return $student->fresh();
        });
    }
}
