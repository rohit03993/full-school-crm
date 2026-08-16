<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\User;
use App\Support\BiometricPinCollision;

/**
 * Device PIN / Face ID for staff. Existing codes (e.g. STF012) are left alone;
 * blank profiles get the next free number from 1001 upward.
 */
class StaffEmployeeCodeService
{
    public const START_FROM = 1001;

    /**
     * Assign a Staff ID when missing. Returns the code now on the profile.
     */
    public function ensureForUser(User $user): ?string
    {
        $user->loadMissing('staffProfile');

        $existing = BiometricPinCollision::normalize($user->staffProfile?->employee_code);

        if ($existing !== '') {
            return $existing;
        }

        $code = $this->nextAvailableCode();

        if ($user->staffProfile) {
            $user->staffProfile->forceFill(['employee_code' => $code])->save();
        } else {
            $user->staffProfile()->create(['employee_code' => $code]);
        }

        $user->unsetRelation('staffProfile');

        return $code;
    }

    /**
     * Fill Staff ID for every active staff member still missing one.
     *
     * @return array{assigned: int, skipped: int}
     */
    public function backfillMissing(): array
    {
        $assigned = 0;
        $skipped = 0;

        $users = User::query()
            ->where('is_platform_operator', false)
            ->where(function ($query): void {
                $query
                    ->whereHas('roles')
                    ->orWhereHas('staffProfile');
            })
            ->with('staffProfile')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $before = BiometricPinCollision::normalize($user->staffProfile?->employee_code);

            if ($before !== '') {
                $skipped++;

                continue;
            }

            $this->ensureForUser($user);
            $assigned++;
        }

        return [
            'assigned' => $assigned,
            'skipped' => $skipped,
        ];
    }

    public function nextAvailableCode(): string
    {
        $candidate = max(self::START_FROM, $this->highestNumericCode() + 1);

        while (
            BiometricPinCollision::employeeCodeExists((string) $candidate)
            || BiometricPinCollision::staffCodeCollidesWithStudentRoll((string) $candidate)
        ) {
            $candidate++;
        }

        return (string) $candidate;
    }

    protected function highestNumericCode(): int
    {
        $codes = StaffProfile::query()
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->pluck('employee_code');

        $max = self::START_FROM - 1;

        foreach ($codes as $code) {
            $normalized = BiometricPinCollision::normalize((string) $code);

            if ($normalized !== '' && ctype_digit($normalized)) {
                $max = max($max, (int) $normalized);
            }
        }

        return $max;
    }
}
