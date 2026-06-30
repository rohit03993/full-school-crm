<?php

namespace App\Services\Punch;

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;

class PunchAttendanceSyncService
{
    public function syncFromPunch(
        Student $student,
        string $date,
        string $state,
        string $punchTime,
        string $source = 'biometric',
        ?int $markedByUserId = null,
    ): void {
        $student->loadMissing('activeBatchStudent');

        $batchId = $student->activeBatchStudent?->batch_id;

        if (! $batchId) {
            return;
        }

        $staffId = $markedByUserId ?? $this->systemUserId();
        $checkedInAt = Carbon::parse($date.' '.$this->normalizeTime($punchTime));

        $existing = Attendance::query()
            ->where('batch_id', $batchId)
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($state === 'IN') {
            Attendance::query()->updateOrCreate(
                [
                    'batch_id' => $batchId,
                    'student_id' => $student->id,
                    'attendance_date' => $date,
                ],
                [
                    'status' => AttendanceStatus::Present,
                    'checked_in_at' => $checkedInAt,
                    'punch_source' => $source,
                    'marked_by_user_id' => $markedByUserId ?? $existing?->marked_by_user_id ?? $staffId,
                ],
            );

            return;
        }

        if ($existing) {
            $existing->update([
                'checked_out_at' => $checkedInAt,
                'punch_source' => $existing->punch_source ?? $source,
            ]);
        }
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
