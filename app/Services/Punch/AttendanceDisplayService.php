<?php

namespace App\Services\Punch;

use App\Models\AttendanceManualPunch;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceDisplayService
{
    /** @var array<string, Student|null> */
    private array $studentByRollCache = [];

    /** @var array<int, string|null> */
    private array $photoUrlCache = [];

    public function __construct(
        protected PunchLogService $logs,
        protected PunchInOutCalculator $calculator,
        protected AttendanceDisplaySettingsService $settings,
        protected LivePunchDashboardService $dashboard,
        protected PunchBatchRosterService $roster,
    ) {}

    /**
     * @return list<array{id: int, name: string}>
     */
    public function batchOptions(): array
    {
        return $this->dashboard->activeBatchOptions();
    }

    /**
     * Single lightweight query for TV live updates (latest card + recent list + new punches).
     *
     * @return array{
     *     latest: ?array<string, mixed>,
     *     recent: list<array<string, mixed>>,
     *     punches: list<array<string, mixed>>,
     *     max_id: int,
     * }
     */
    public function liveFeed(int $sinceId, ?int $batchId = null, ?string $stateFilter = null): array
    {
        if (! $this->logs->punchTableExists()) {
            return [
                'latest' => null,
                'recent' => [],
                'punches' => [],
                'max_id' => 0,
            ];
        }

        $limit = max(10, (int) config('attendance_display.live_punch_scan_limit', 20));

        $rows = DB::table($this->logs->punchTable())
            ->where('punch_date', now()->toDateString())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $filtered = [];

        foreach ($rows as $row) {
            $event = $this->formatPunchRow($row);

            if ($event !== null && $this->matchesFilters($event, $batchId, $stateFilter)) {
                $filtered[] = $event;
            }
        }

        $recent = [];

        foreach (array_slice($filtered, 0, 10) as $event) {
            $recent[] = $this->punchSnippet($event);
        }

        $punches = array_values(array_filter(
            $filtered,
            fn (array $event): bool => (int) $event['id'] > max(0, $sinceId),
        ));

        return [
            'latest' => $filtered[0] ?? null,
            'recent' => $recent,
            'punches' => $punches,
            'max_id' => $this->maxPunchLogId(),
        ];
    }

    /**
     * @return array{
     *     present_today: int,
     *     inside_now: int,
     *     checked_out: int,
     *     absent: int,
     *     total_students: int,
     *     by_batch: list<array<string, mixed>>,
     * }
     */
    public function summaryForToday(?int $batchId = null): array
    {
        $date = now()->toDateString();
        $cacheSeconds = max(5, (int) config('attendance_display.summary_cache_seconds', 15));
        $cacheKey = 'attendance_display.summary.'.$date.'.'.($batchId ?? 'all');

        return Cache::remember(
            $cacheKey,
            $cacheSeconds,
            fn (): array => $this->buildSummaryForToday($batchId, $date),
        );
    }

    /**
     * @return array{
     *     present_today: int,
     *     inside_now: int,
     *     checked_out: int,
     *     absent: int,
     *     total_students: int,
     *     by_batch: list<array<string, mixed>>,
     * }
     */
    private function buildSummaryForToday(?int $batchId, string $date): array
    {
        if ($batchId !== null) {
            $roster = $this->roster->rosterForBatch($batchId, $date);
            $inside = collect($roster['present'])
                ->where('current_state', 'IN')
                ->count();
            $present = (int) ($roster['counts']['present'] ?? 0);

            return [
                'present_today' => $present,
                'inside_now' => $inside,
                'checked_out' => max(0, $present - $inside),
                'absent' => (int) ($roster['counts']['absent'] ?? 0),
                'total_students' => (int) ($roster['counts']['total'] ?? 0),
                'by_batch' => [[
                    'batch_id' => $batchId,
                    'batch_name' => $roster['batch_name'] ?? 'Batch',
                    'present' => $present,
                    'inside' => $inside,
                    'absent' => (int) ($roster['counts']['absent'] ?? 0),
                    'total' => (int) ($roster['counts']['total'] ?? 0),
                ]],
            ];
        }

        $stats = $this->dashboard->dashboardForDate($date)['stats'] ?? [
            'total' => 0,
            'inside' => 0,
            'out' => 0,
        ];

        $byBatch = [];
        $totalStudents = 0;
        $totalAbsent = 0;

        foreach ($this->batchOptions() as $batch) {
            $roster = $this->roster->rosterForBatch((int) $batch['id'], $date);
            $inside = collect($roster['present'])
                ->where('current_state', 'IN')
                ->count();
            $present = (int) ($roster['counts']['present'] ?? 0);

            $byBatch[] = [
                'batch_id' => (int) $batch['id'],
                'batch_name' => (string) $batch['name'],
                'present' => $present,
                'inside' => $inside,
                'absent' => (int) ($roster['counts']['absent'] ?? 0),
                'total' => (int) ($roster['counts']['total'] ?? 0),
            ];

            $totalStudents += (int) ($roster['counts']['total'] ?? 0);
            $totalAbsent += (int) ($roster['counts']['absent'] ?? 0);
        }

        usort($byBatch, fn (array $a, array $b): int => strcmp((string) $a['batch_name'], (string) $b['batch_name']));

        return [
            'present_today' => (int) ($stats['total'] ?? 0),
            'inside_now' => (int) ($stats['inside'] ?? 0),
            'checked_out' => (int) ($stats['out'] ?? 0),
            'absent' => $totalAbsent,
            'total_students' => $totalStudents,
            'by_batch' => $byBatch,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentPunchesToday(int $limit = 10, ?int $batchId = null, ?string $stateFilter = null): array
    {
        return $this->liveFeed(0, $batchId, $stateFilter)['recent'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function punchesSince(int $sinceId, int $limit = 20, ?int $batchId = null, ?string $stateFilter = null): array
    {
        return $this->liveFeed($sinceId, $batchId, $stateFilter)['punches'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestMatchingFilters(?int $batchId = null, ?string $stateFilter = null): ?array
    {
        return $this->liveFeed(0, $batchId, $stateFilter)['latest'];
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
        $student = $this->studentForRoll($roll);
        $state = $this->resolveState($roll, $date, $time, $row);
        $isManual = $this->isManualRow($row);
        $device = filled($row->device_name ?? null)
            ? (string) $row->device_name
            : (filled($row->area_name ?? null) ? (string) $row->area_name : null);

        $photo = $student?->profilePhoto();

        return [
            'id' => (int) $row->id,
            'roll' => $roll,
            'name' => $student?->name ?? 'Unknown student',
            'batch_id' => $student?->activeBatchStudent?->batch_id,
            'batch' => $student?->activeBatchStudent?->batch?->name,
            'course' => $student?->activeEnrollment?->course?->name,
            'mobile' => $student?->mobile,
            'state' => $state,
            'time' => $time,
            'date' => $date,
            'date_label' => \Illuminate\Support\Carbon::parse($date)->format('d M Y'),
            'source' => $isManual ? 'Manual' : ($device ?? 'Device'),
            'initials' => $student?->initials() ?? '?',
            'photo_id' => ($photo !== null && $photo->isImage()) ? (int) $photo->id : null,
            'photo_url' => $this->photoUrlForStudent($student),
            'is_mapped' => $student !== null,
        ];
    }

    private function studentForRoll(string $roll): ?Student
    {
        $roll = $this->logs->normalizeRoll($roll);

        if ($roll === '') {
            return null;
        }

        if (! array_key_exists($roll, $this->studentByRollCache)) {
            $this->studentByRollCache[$roll] = $this->logs->findStudentByRoll($roll);
            $this->studentByRollCache[$roll]?->loadMissing([
                'activeEnrollment.course',
                'activeBatchStudent.batch',
                'activeEnrollment.admission.documents',
            ]);
        }

        return $this->studentByRollCache[$roll];
    }

    /**
     * @param  array<string, mixed>  $punch
     * @return array<string, mixed>
     */
    public function punchSnippet(array $punch): array
    {
        return [
            'id' => $punch['id'],
            'roll' => $punch['roll'],
            'name' => $punch['name'],
            'batch' => $punch['batch'] ?? null,
            'state' => $punch['state'],
            'time' => $punch['time'],
            'photo_id' => $punch['photo_id'] ?? null,
            'photo_url' => $punch['photo_url'] ?? null,
            'initials' => $punch['initials'] ?? '?',
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function matchesFiltersPublic(array $event, ?int $batchId, ?string $stateFilter): bool
    {
        return $this->matchesFilters($event, $batchId, $stateFilter);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function matchesFilters(array $event, ?int $batchId, ?string $stateFilter): bool
    {
        if ($batchId !== null && (int) ($event['batch_id'] ?? 0) !== $batchId) {
            return false;
        }

        if ($stateFilter !== null && strtoupper($stateFilter) !== ($event['state'] ?? '')) {
            return false;
        }

        return true;
    }

    private function photoUrlForStudent(?Student $student): ?string
    {
        if ($student === null) {
            return null;
        }

        $photo = $student->profilePhoto();

        if ($photo === null || ! $photo->isImage()) {
            return null;
        }

        if (! array_key_exists($photo->id, $this->photoUrlCache)) {
            $this->photoUrlCache[$photo->id] = $this->settings->signedPhotoUrl($photo->id);
        }

        return $this->photoUrlCache[$photo->id];
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
