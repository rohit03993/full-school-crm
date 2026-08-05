<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\BiometricPinCollision;
use App\Support\IndianMobileNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffBulkImportService
{
    /**
     * Map spreadsheet headers (case-insensitive) to canonical keys.
     *
     * @param  list<string|null>  $headers
     * @return array{staff_id: ?int, name: ?int, mobile: ?int, designation: ?int, email: ?int}
     */
    public function guessColumnIndexes(array $headers): array
    {
        $map = [
            'staff_id' => null,
            'name' => null,
            'mobile' => null,
            'designation' => null,
            'email' => null,
        ];

        foreach ($headers as $index => $header) {
            $key = strtolower(trim((string) $header));
            $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
            $key = trim($key, '_');

            if (in_array($key, ['staff_id', 'employee_code', 'employee_id', 'emp_id', 'id'], true)) {
                $map['staff_id'] ??= $index;
            } elseif (in_array($key, ['name', 'staff_name', 'full_name'], true)) {
                $map['name'] ??= $index;
            } elseif (in_array($key, ['mobile', 'phone', 'mobile_number', 'contact'], true)) {
                $map['mobile'] ??= $index;
            } elseif (in_array($key, ['designation', 'role', 'title'], true)) {
                $map['designation'] ??= $index;
            } elseif ($key === 'email') {
                $map['email'] ??= $index;
            }
        }

        return $map;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @param  array{staff_id: ?int, name: ?int, mobile: ?int, designation: ?int, email: ?int}  $columns
     * @return array{imported: int, updated: int, errors: list<string>}
     */
    public function importRows(User $actor, array $rows, array $columns): array
    {
        if ($columns['staff_id'] === null || $columns['name'] === null || $columns['mobile'] === null) {
            throw ValidationException::withMessages([
                'file' => 'Map Staff ID, Name, and Mobile columns (headers: Staff ID, Name, Mobile).',
            ]);
        }

        $imported = 0;
        $updated = 0;
        $errors = [];
        $seenCodes = [];
        $seenMobiles = [];

        foreach ($rows as $rowIndex => $row) {
            $line = $rowIndex + 2;
            $staffId = BiometricPinCollision::normalize($this->cell($row, $columns['staff_id']));
            $name = trim((string) $this->cell($row, $columns['name']));
            $mobileRaw = (string) $this->cell($row, $columns['mobile']);
            $designation = trim((string) $this->cell($row, $columns['designation']));
            $email = trim((string) $this->cell($row, $columns['email']));

            if ($staffId === '' && $name === '' && trim($mobileRaw) === '') {
                continue;
            }

            if ($staffId === '') {
                $errors[] = "Row {$line}: Staff ID is required.";

                continue;
            }

            if ($name === '') {
                $errors[] = "Row {$line}: Name is required.";

                continue;
            }

            $mobile = IndianMobileNumber::normalizeFromSpreadsheet($mobileRaw);

            if (! $mobile) {
                $errors[] = "Row {$line}: Invalid mobile number.";

                continue;
            }

            if (isset($seenCodes[$staffId])) {
                $errors[] = "Row {$line}: Duplicate Staff ID {$staffId} in this file.";

                continue;
            }

            if (isset($seenMobiles[$mobile])) {
                $errors[] = "Row {$line}: Duplicate mobile {$mobile} in this file.";

                continue;
            }

            $seenCodes[$staffId] = true;
            $seenMobiles[$mobile] = true;

            if (BiometricPinCollision::staffCodeCollidesWithStudentRoll($staffId)) {
                $errors[] = "Row {$line}: Staff ID {$staffId} matches a student roll number — choose a different ID.";

                continue;
            }

            try {
                $result = DB::transaction(function () use ($staffId, $name, $mobile, $designation, $email): string {
                    return $this->upsertStaffRow($staffId, $name, $mobile, $designation, $email);
                });

                if ($result === 'created') {
                    $imported++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $exception) {
                $errors[] = "Row {$line}: ".$exception->getMessage();
            }
        }

        unset($actor);

        return compact('imported', 'updated', 'errors');
    }

    protected function upsertStaffRow(
        string $staffId,
        string $name,
        string $mobile,
        string $designation,
        string $email,
    ): string {
        $profile = StaffProfile::query()
            ->whereRaw('UPPER(employee_code) = ?', [$staffId])
            ->with('user')
            ->first();

        if ($profile?->user) {
            $otherMobile = User::query()
                ->where('mobile', $mobile)
                ->whereKeyNot($profile->user_id)
                ->exists();

            if ($otherMobile) {
                throw new \RuntimeException("Mobile {$mobile} already belongs to another staff account.");
            }

            $profile->user->update([
                'name' => $name,
                'mobile' => $mobile,
                'email' => filled($email) ? $email : $profile->user->email,
                'is_active' => true,
            ]);

            $profile->update([
                'employee_code' => $staffId,
                'designation' => filled($designation) ? $designation : $profile->designation,
                'mobile' => $mobile,
            ]);

            return 'updated';
        }

        if (User::query()->where('mobile', $mobile)->exists()) {
            throw new \RuntimeException("Mobile {$mobile} already belongs to another staff account.");
        }

        if (StaffProfile::query()->whereRaw('UPPER(employee_code) = ?', [$staffId])->exists()) {
            throw new \RuntimeException("Staff ID {$staffId} already exists.");
        }

        $user = User::query()->create([
            'name' => $name,
            'mobile' => $mobile,
            'email' => filled($email) ? $email : null,
            'password' => Hash::make(Str::password(12)),
            'is_active' => true,
        ]);

        $user->syncRoles([RoleName::Staff->value]);

        $user->staffProfile()->create([
            'employee_code' => $staffId,
            'designation' => filled($designation) ? $designation : null,
            'mobile' => $mobile,
        ]);

        return 'created';
    }

    /**
     * @param  list<string|null>  $row
     */
    protected function cell(array $row, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        return $row[$index] ?? null;
    }
}
