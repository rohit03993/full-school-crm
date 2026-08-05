<?php

namespace App\Services\Punch;

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;

class StaffPunchAttendanceSyncService
{
    public function syncFromPunch(
        User $staffMember,
        string $date,
        string $state,
        string $punchTime,
        string $source = 'biometric',
        ?int $markedByUserId = null,
    ): void {
        $markerId = $markedByUserId ?? $this->systemUserId();
        $checkedInAt = Carbon::parse($date.' '.$this->normalizeTime($punchTime));

        $existing = StaffAttendance::query()
            ->where('user_id', $staffMember->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($state === 'IN') {
            if ($existing) {
                $payload = [
                    'status' => AttendanceStatus::Present,
                    'punch_source' => $source,
                    'marked_by_user_id' => $markedByUserId ?? $existing->marked_by_user_id ?? $markerId,
                ];

                if ($existing->checked_in_at === null || $checkedInAt->lt($existing->checked_in_at)) {
                    $payload['checked_in_at'] = $checkedInAt;
                }

                if ($existing->checked_out_at !== null && $checkedInAt->gte($existing->checked_out_at)) {
                    $payload['checked_out_at'] = null;
                }

                $existing->update($payload);
            } else {
                StaffAttendance::query()->create([
                    'user_id' => $staffMember->id,
                    'attendance_date' => $date,
                    'status' => AttendanceStatus::Present,
                    'checked_in_at' => $checkedInAt,
                    'punch_source' => $source,
                    'marked_by_user_id' => $markedByUserId ?? $markerId,
                ]);
            }

            return;
        }

        if ($existing) {
            if ($existing->checked_out_at === null || $checkedInAt->gt($existing->checked_out_at)) {
                $payload = [
                    'checked_out_at' => $checkedInAt,
                    'punch_source' => $source,
                ];

                if ($markedByUserId) {
                    $payload['marked_by_user_id'] = $markedByUserId;
                }

                $existing->update($payload);
            }

            return;
        }

        StaffAttendance::query()->create([
            'user_id' => $staffMember->id,
            'attendance_date' => $date,
            'status' => AttendanceStatus::Present,
            'checked_in_at' => null,
            'checked_out_at' => $checkedInAt,
            'punch_source' => $source,
            'marked_by_user_id' => $markedByUserId ?? $markerId,
        ]);
    }

    private function systemUserId(): int
    {
        $userId = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::SuperAdmin->value))
            ->value('id');

        return (int) ($userId ?? User::query()->value('id') ?? 1);
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
