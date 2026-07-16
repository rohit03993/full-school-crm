<?php

namespace App\Services\Punch;

use App\Models\AttendanceManualPunch;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceDisplayService
{
    public function __construct(
        protected PunchLogService $logs,
        protected PunchInOutCalculator $calculator,
        protected AttendanceDisplaySettingsService $settings,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function punchesSince(int $sinceId, int $limit = 20): array
    {
        if (! $this->logs->punchTableExists()) {
            return [];
        }

        $table = $this->logs->punchTable();

        $rows = DB::table($table)
            ->where('id', '>', max(0, $sinceId))
            ->orderBy('id')
            ->limit(max(1, min($limit, 50)))
            ->get();

        $events = [];

        foreach ($rows as $row) {
            $event = $this->formatPunchRow($row);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestPunchToday(): ?array
    {
        if (! $this->logs->punchTableExists()) {
            return null;
        }

        $row = DB::table($this->logs->punchTable())
            ->where('punch_date', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        return $row ? $this->formatPunchRow($row) : null;
    }

    public function maxPunchLogId(): int
    {
        if (! $this->logs->punchTableExists()) {
            return 0;
        }

        return (int) (DB::table($this->logs->punchTable())->max('id') ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatPunchRow(object $row): ?array
    {
        $roll = $this->logs->normalizeRoll((string) ($row->employee_id ?? ''));

        if ($roll === '') {
            return null;
        }

        $date = (string) $row->punch_date;
        $time = $this->normalizeTime((string) $row->punch_time);
        $student = $this->logs->findStudentByRoll($roll);
        $student?->loadMissing(['activeEnrollment.course', 'activeBatchStudent.batch']);
        $state = $this->resolveState($roll, $date, $time, $row);
        $isManual = $this->isManualRow($row);
        $device = filled($row->device_name ?? null)
            ? (string) $row->device_name
            : (filled($row->area_name ?? null) ? (string) $row->area_name : null);

        return [
            'id' => (int) $row->id,
            'roll' => $roll,
            'name' => $student?->name ?? 'Unknown student',
            'batch' => $student?->activeBatchStudent?->batch?->name,
            'course' => $student?->activeEnrollment?->course?->name,
            'mobile' => $student?->mobile,
            'state' => $state,
            'time' => $time,
            'date' => $date,
            'date_label' => \Illuminate\Support\Carbon::parse($date)->format('d M Y'),
            'source' => $isManual ? 'Manual' : ($device ?? 'Device'),
            'initials' => $student?->initials() ?? '?',
            'photo_url' => $this->photoUrlForStudent($student),
            'is_mapped' => $student !== null,
        ];
    }

    private function photoUrlForStudent(?Student $student): ?string
    {
        if ($student === null) {
            return null;
        }

        $student->loadMissing(['activeEnrollment.admission.documents']);

        $photo = $student->profilePhoto();

        if ($photo === null || ! $photo->isImage()) {
            return null;
        }

        return $this->settings->signedPhotoUrl($photo->id);
    }

    private function resolveState(string $roll, string $date, string $time, object $row): string
    {
        if ($this->isManualRow($row) && Schema::hasTable('attendance_manual_punches')) {
            $manual = AttendanceManualPunch::query()
                ->where('enrollment_number', $roll)
                ->whereDate('punch_date', $date)
                ->where(function ($query) use ($time): void {
                    $query->where('punch_time', $time)
                        ->orWhere('punch_time', substr($time, 0, 5));
                })
                ->orderByDesc('id')
                ->first();

            if ($manual !== null && filled($manual->state)) {
                return strtoupper((string) $manual->state);
            }
        }

        $punches = $this->logs->unifiedPunchesForRollDate($roll, $date);

        return $this->calculator->stateForPunch($punches, $time, $date);
    }

    private function isManualRow(object $row): bool
    {
        if (isset($row->is_manual) && (int) $row->is_manual === 1) {
            return true;
        }

        return isset($row->device_name) && strcasecmp((string) $row->device_name, 'Manual') === 0;
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
