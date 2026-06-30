<?php

namespace App\Services\Punch;

use App\Enums\BatchStatus;
use App\Models\AttendancePunchWhatsappLog;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Pages\StudentSearchPage;
use App\Models\Student;
use App\Support\PunchDuration;
use App\Support\PunchWhatsappStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LivePunchDashboardService
{
    public function __construct(
        protected PunchLogService $logs,
        protected PunchInOutCalculator $calculator,
    ) {}

    public function punchTableReady(): bool
    {
        return $this->logs->punchTableExists();
    }

    /**
     * @return array{
     *     stats: array{total: int, inside: int, out: int},
     *     rows: list<array<string, mixed>>,
     * }
     */
    public function dashboardForDate(
        string $date,
        ?int $batchId = null,
        ?string $rollFilter = null,
        ?string $nameFilter = null,
        ?string $stateFilter = null,
    ): array {
        if (! $this->logs->punchTableExists()) {
            return [
                'stats' => ['total' => 0, 'inside' => 0, 'out' => 0],
                'rows' => [],
            ];
        }

        $rolls = $this->rollsWithPunchesOnDate($date);

        if ($batchId) {
            $batchRolls = $this->rollsForBatch($batchId);
            $rolls = $rolls->intersect($batchRolls)->values();
        }

        $rows = [];
        $stats = ['total' => 0, 'inside' => 0, 'out' => 0];

        foreach ($rolls as $roll) {
            $student = $this->logs->findStudentByRoll($roll);

            if ($nameFilter && $student && ! str_contains(strtolower($student->name), strtolower($nameFilter))) {
                continue;
            }

            if ($nameFilter && ! $student) {
                continue;
            }

            if ($rollFilter && ! str_contains(strtolower($roll), strtolower($rollFilter))) {
                continue;
            }

            $dayRow = $this->studentDayRow($roll, $date, $student);

            if ($dayRow === null) {
                continue;
            }

            $stats['total']++;

            if ($dayRow['current_state'] === 'IN') {
                $stats['inside']++;
            } else {
                $stats['out']++;
            }

            if ($stateFilter && $dayRow['current_state'] !== $stateFilter) {
                continue;
            }

            $rows[] = $dayRow;
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['student_name'], $b['student_name']));

        return ['stats' => $stats, 'rows' => $rows];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function studentDayRow(string $roll, string $date, ?Student $student = null): ?array
    {
        $student ??= $this->logs->findStudentByRoll($roll);
        $machinePunches = $this->logs->machinePunchesForRollDate($roll, $date);
        $punches = $this->logs->unifiedPunchesForRollDate($roll, $date);
        [$daily] = $this->calculator->computeInOut($punches);
        $pairs = $this->enrichPairs($date, $daily[$date] ?? [], $machinePunches);

        if ($pairs === []) {
            return null;
        }

        $lastPair = $pairs[array_key_last($pairs)];
        $currentState = ($lastPair['out'] ?? null) ? 'OUT' : 'IN';
        $whatsapp = $this->whatsappStatusForRoll($roll, $date);
        $lastDevice = $this->lastDeviceName($machinePunches);

        return [
            'roll' => $roll,
            'student' => $student,
            'student_id' => $student?->id,
            'student_name' => $student?->name ?? 'Unmapped punch',
            'batch_name' => $student?->activeBatchStudent?->batch?->name,
            'mobile' => $student?->mobile,
            'pairs' => $pairs,
            'current_state' => $currentState,
            'last_device' => $lastDevice,
            'total_duration' => $this->totalDurationLabel($date, $pairs),
            'whatsapp' => $whatsapp,
            'whatsapp_chips' => [
                'in' => PunchWhatsappStatus::chip($whatsapp['in']),
                'out' => PunchWhatsappStatus::chip($whatsapp['out']),
            ],
            'is_mapped' => $student !== null,
            'profile_url' => $student
                ? StudentProfilePage::getUrl(['record' => $student->id])
                : null,
            'find_student_url' => StudentSearchPage::getUrl().'?roll='.urlencode($roll),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pairs
     * @return list<array<string, mixed>>
     */
    private function enrichPairs(string $date, array $pairs, Collection $machinePunches): array
    {
        $deviceByTime = $machinePunches->flatMap(function (object $punch): array {
            $time = (string) $punch->punch_time;
            $device = filled($punch->device_name ?? null)
                ? (string) $punch->device_name
                : (filled($punch->area_name ?? null) ? (string) $punch->area_name : null);

            if ($device === null) {
                return [];
            }

            $keys = array_unique([$time, strlen($time) > 5 ? substr($time, 0, 5) : $time.':00']);

            return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $device])->all();
        });

        foreach ($pairs as &$pair) {
            $pair['duration_label'] = PunchDuration::format($date, $pair['in'] ?? null, $pair['out'] ?? null);
            $pair['device_in'] = $this->deviceForTime($deviceByTime, (string) ($pair['in'] ?? ''))
                ?? (($pair['is_manual_in'] ?? false) ? 'Manual' : null);
            $pair['device_out'] = filled($pair['out'] ?? null)
                ? ($this->deviceForTime($deviceByTime, (string) $pair['out'])
                    ?? (($pair['is_manual_out'] ?? false) ? 'Manual' : (($pair['is_auto_out'] ?? false) ? 'Auto OUT' : null)))
                : null;
        }

        return $pairs;
    }

    /**
     * @param  Collection<string, string>  $deviceByTime
     */
    private function deviceForTime(Collection $deviceByTime, string $time): ?string
    {
        if ($time === '') {
            return null;
        }

        return $deviceByTime->get($time)
            ?? $deviceByTime->get(strlen($time) === 5 ? $time.':00' : substr($time, 0, 5));
    }

    /**
     * @param  list<array<string, mixed>>  $pairs
     */
    private function totalDurationLabel(string $date, array $pairs): ?string
    {
        $minutes = 0;

        foreach ($pairs as $pair) {
            if (! filled($pair['in'] ?? null) || ! filled($pair['out'] ?? null)) {
                continue;
            }

            $label = PunchDuration::format($date, $pair['in'], $pair['out']);

            if ($label === null) {
                continue;
            }

            if (preg_match('/^(?:(\d+)h\s*)?(\d+)m$/', $label, $matches)) {
                $minutes += ((int) ($matches[1] ?? 0)) * 60 + (int) $matches[2];
            }
        }

        if ($minutes === 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0 ? $hours.'h '.$remaining.'m total' : $remaining.'m total';
    }

    /**
     * @param  Collection<int, object>  $machinePunches
     */
    private function lastDeviceName(Collection $machinePunches): ?string
    {
        $last = $machinePunches->last();

        if (! $last) {
            return null;
        }

        if (filled($last->device_name ?? null)) {
            return (string) $last->device_name;
        }

        if (filled($last->area_name ?? null)) {
            return (string) $last->area_name;
        }

        return null;
    }

    /**
     * @return Collection<int, string>
     */
    private function rollsWithPunchesOnDate(string $date): Collection
    {
        $machineRolls = DB::table($this->logs->punchTable())
            ->where('punch_date', $date)
            ->pluck('employee_id')
            ->map(fn ($roll): string => $this->logs->normalizeRoll((string) $roll));

        $manualRolls = collect();

        if (\Illuminate\Support\Facades\Schema::hasTable('attendance_manual_punches')) {
            $manualRolls = DB::table('attendance_manual_punches')
                ->whereDate('punch_date', $date)
                ->pluck('enrollment_number')
                ->map(fn ($roll): string => $this->logs->normalizeRoll((string) $roll));
        }

        return $machineRolls->merge($manualRolls)->unique()->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function rollsForBatch(int $batchId): Collection
    {
        return Enrollment::query()
            ->where('is_active', true)
            ->whereHas('student.activeBatchStudent', fn ($q) => $q->where('batch_id', $batchId)->where('is_active', true))
            ->pluck('enrollment_number')
            ->map(fn ($roll): string => $this->logs->normalizeRoll((string) $roll));
    }

    /**
     * @return array{in: ?string, out: ?string}
     */
    private function whatsappStatusForRoll(string $roll, string $date): array
    {
        $logs = AttendancePunchWhatsappLog::query()
            ->where('enrollment_number', $roll)
            ->whereDate('punch_date', $date)
            ->orderBy('sent_at')
            ->get();

        return [
            'in' => $logs->firstWhere('state', 'IN')?->status,
            'out' => $logs->firstWhere('state', 'OUT')?->status,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function activeBatchOptions(): array
    {
        return Batch::query()
            ->where('status', BatchStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Batch $batch): array => ['id' => $batch->id, 'name' => $batch->name])
            ->all();
    }
}
