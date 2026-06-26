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
        protected StudentMobileService $mobiles,
        protected CustomFieldService $customFields,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data, ?User $staff = null): Student
    {
        return DB::transaction(function () use ($student, $data, $staff): Student {
            $phones = $this->mobiles->validateForUpdate(
                $student,
                (string) ($data['mobile'] ?? $student->mobile),
                $data['alternate_mobile'] ?? null,
            );

            $oldValues = $student->only([
                'name',
                'father_name',
                'date_of_birth',
                'gender',
                'mobile',
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
                'mobile' => $phones['mobile'],
                'alternate_mobile' => $phones['alternate_mobile'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'pincode' => $data['pincode'] ?? null,
                'category' => $data['category'] ?? $student->category?->value ?? 'general',
            ];

            if (filled($phones['mobile'])) {
                $attributes['mobile_import_note'] = null;
            }

            if (array_key_exists('custom_data', $data)) {
                $attributes['custom_data'] = $this->customFields->validateForEntity(
                    CustomFieldService::ENTITY_STUDENT,
                    is_array($data['custom_data']) ? $data['custom_data'] : [],
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
