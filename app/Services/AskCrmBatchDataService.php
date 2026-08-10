<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\HomeworkCheckPage;
use App\Filament\Pages\MyTeachingAssignmentsPage;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\HomeworkCheck;
use App\Models\User;
use App\Support\FeatureGate;

class AskCrmBatchDataService
{
    public function __construct(
        protected StudentImportBatchResolver $batchResolver,
        protected HomeworkCheckService $homeworkChecks,
        protected BatchStaffAssignmentService $teaching,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function batchStatusSnapshot(User $user, string $batchLabel, ?string $referencedDate = null): array
    {
        $date = $referencedDate ?: now()->toDateString();
        $resolved = $this->resolveBatchForUser($user, $batchLabel);

        if (($resolved['batch'] ?? null) === null) {
            return [
                'meta' => [
                    'today' => now()->toDateString(),
                    'referenced_date' => $date,
                    'source' => 'batch_status',
                    'user_id' => $user->id,
                ],
                'batch' => [
                    'id' => null,
                    'name' => null,
                    'resolved_from' => $batchLabel,
                    'ambiguous' => $resolved['suggestions'] ?? [],
                    'not_found' => true,
                ],
                'access' => ['allowed' => false],
                'attendance_today' => null,
                'homework' => null,
                'links' => $this->links(),
            ];
        }

        /** @var Batch $batch */
        $batch = $resolved['batch'];
        $allowed = $this->homeworkChecks->userCanAccessBatch($user, (int) $batch->id);

        if (! $allowed) {
            return [
                'meta' => [
                    'today' => now()->toDateString(),
                    'referenced_date' => $date,
                    'source' => 'batch_status',
                    'user_id' => $user->id,
                ],
                'batch' => [
                    'id' => (int) $batch->id,
                    'name' => $batch->displayLabel(),
                    'resolved_from' => $batchLabel,
                    'ambiguous' => [],
                    'not_found' => false,
                ],
                'access' => ['allowed' => false, 'reason' => 'not_assigned_to_batch'],
                'attendance_today' => null,
                'homework' => null,
                'links' => $this->links(),
            ];
        }

        return [
            'meta' => [
                'today' => now()->toDateString(),
                'referenced_date' => $date,
                'source' => 'batch_status',
                'user_id' => $user->id,
            ],
            'batch' => [
                'id' => (int) $batch->id,
                'name' => $batch->displayLabel(),
                'resolved_from' => $batchLabel,
                'ambiguous' => [],
                'not_found' => false,
            ],
            'access' => ['allowed' => true],
            'attendance_today' => FeatureGate::enabled(LicenseFeature::Attendance)
                ? $this->attendanceForBatch((int) $batch->id, $date)
                : ['enabled' => false],
            'homework' => FeatureGate::enabled(LicenseFeature::Homework)
                ? $this->homeworkForBatch((int) $batch->id, $date)
                : ['enabled' => false],
            'links' => $this->links(),
        ];
    }

    /**
     * @return array{batch: ?Batch, suggestions: list<string>}
     */
    public function resolveBatchForUser(User $user, string $label): array
    {
        $label = trim($label);

        if ($label === '' || in_array(mb_strtolower($label), ['my batch', 'my class', 'my classes'], true)) {
            $assignments = $this->teaching->assignmentsForUser($user);
            $batch = $assignments[0]['batch'] ?? null;

            return [
                'batch' => $batch instanceof Batch ? $batch : null,
                'suggestions' => collect($assignments)
                    ->map(fn (array $row): string => $row['batch']?->displayLabel() ?? '')
                    ->filter()
                    ->unique()
                    ->take(5)
                    ->values()
                    ->all(),
            ];
        }

        $batch = $this->batchResolver->resolve($label);

        if ($batch) {
            return ['batch' => $batch, 'suggestions' => []];
        }

        return [
            'batch' => null,
            'suggestions' => $this->batchResolver->suggestions($label),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attendanceForBatch(int $batchId, string $date): array
    {
        $students = BatchStudent::query()
            ->where('batch_id', $batchId)
            ->where('is_active', true)
            ->with(['student.activeEnrollment'])
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy('name')
            ->values();

        $creditedIds = Attendance::query()
            ->where('batch_id', $batchId)
            ->whereDate('attendance_date', $date)
            ->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Leave])
            ->pluck('student_id')
            ->all();

        $absentMarked = Attendance::query()
            ->where('batch_id', $batchId)
            ->whereDate('attendance_date', $date)
            ->where('status', AttendanceStatus::Absent)
            ->pluck('student_id')
            ->all();

        $creditedLookup = array_fill_keys($creditedIds, true);
        $absentMarkedLookup = array_fill_keys($absentMarked, true);

        $absent = [];
        $presentCount = 0;
        $absentCount = 0;
        $notMarkedCount = 0;

        foreach ($students as $student) {
            if (isset($creditedLookup[$student->id])) {
                $presentCount++;

                continue;
            }

            if (isset($absentMarkedLookup[$student->id])) {
                $absentCount++;
                $absent[] = [
                    'id' => (int) $student->id,
                    'name' => $student->name,
                    'roll' => $student->activeEnrollment?->enrollment_number,
                    'status' => 'Absent',
                ];

                continue;
            }

            $notMarkedCount++;
            $absent[] = [
                'id' => (int) $student->id,
                'name' => $student->name,
                'roll' => $student->activeEnrollment?->enrollment_number,
                'status' => 'Not marked',
            ];
        }

        return [
            'enabled' => true,
            'date' => $date,
            'counts' => [
                'total' => $students->count(),
                'present_or_leave' => $presentCount,
                'absent' => $absentCount,
                'not_marked' => $notMarkedCount,
            ],
            'absent_or_unmarked' => array_slice($absent, 0, 20),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function homeworkForBatch(int $batchId, string $date): array
    {
        $todayChecks = HomeworkCheck::query()
            ->where('batch_id', $batchId)
            ->whereDate('checked_on', $date)
            ->with('student')
            ->latest('id')
            ->get()
            ->unique('student_id')
            ->values();

        $notDoneToday = $todayChecks
            ->filter(fn (HomeworkCheck $check): bool => $check->status === HomeworkCheckStatus::NotDone)
            ->map(fn (HomeworkCheck $check): array => [
                'id' => (int) $check->student_id,
                'name' => $check->student?->name,
                'subject' => $check->subject_name,
                'topic' => $check->topic,
            ])
            ->filter(fn (array $row): bool => filled($row['name'] ?? null))
            ->take(20)
            ->values()
            ->all();

        $roster = $this->homeworkChecks->rosterForBatch($batchId, null, null, $date);
        $weekNotDone = $roster
            ->filter(fn (array $row): bool => ((int) ($row['not_done_week'] ?? 0)) > 0)
            ->map(fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'not_done_week' => (int) $row['not_done_week'],
            ])
            ->take(20)
            ->values()
            ->all();

        return [
            'enabled' => true,
            'date' => $date,
            'today' => [
                'marked_count' => $todayChecks->count(),
                'done_count' => $todayChecks->where('status', HomeworkCheckStatus::Done)->count(),
                'not_done_count' => count($notDoneToday),
                'not_done' => $notDoneToday,
            ],
            'this_week_not_done_students' => $weekNotDone,
            'this_week_not_done_count' => count($weekNotDone),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function links(): array
    {
        return [
            'attendance' => AttendancePage::getUrl(),
            'homework' => HomeworkCheckPage::getUrl(),
            'my_classes' => MyTeachingAssignmentsPage::getUrl(),
        ];
    }

    /**
     * @return list<string>
     */
    public function myBatchLabels(User $user): array
    {
        return collect($this->teaching->assignmentsForUser($user))
            ->map(fn (array $row): string => $row['batch']?->displayLabel() ?? '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
