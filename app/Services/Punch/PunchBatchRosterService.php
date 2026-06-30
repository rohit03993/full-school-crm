<?php

namespace App\Services\Punch;

use App\Enums\BatchStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Enrollment;
use App\Models\Student;

class PunchBatchRosterService
{
    public function __construct(
        protected PunchLogService $logs,
        protected PunchInOutCalculator $calculator,
        protected LivePunchDashboardService $dashboard,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     batch_name: ?string,
     *     present: list<array<string, mixed>>,
     *     absent: list<array<string, mixed>>,
     *     counts: array{total: int, present: int, absent: int},
     * }
     */
    public function rosterForBatch(int $batchId, string $date): array
    {
        $batch = Batch::query()->find($batchId);

        if (! $batch || $batch->status !== BatchStatus::Active) {
            return $this->emptyRoster();
        }

        $students = BatchStudent::query()
            ->where('batch_id', $batch->id)
            ->where('is_active', true)
            ->join('students', 'students.id', '=', 'batch_students.student_id')
            ->orderBy('students.name')
            ->select('batch_students.*')
            ->with(['student.activeEnrollment', 'student.activeBatchStudent.batch'])
            ->get();

        $present = [];
        $absent = [];

        foreach ($students as $batchStudent) {
            $student = $batchStudent->student;

            if (! $student) {
                continue;
            }

            $roll = $this->rollForStudent($student);

            if ($roll === null) {
                $absent[] = $this->absentRow($student, null);

                continue;
            }

            $dayRow = $this->dashboard->studentDayRow($roll, $date, $student);

            if ($dayRow !== null) {
                $present[] = $dayRow;
            } else {
                $absent[] = $this->absentRow($student, $roll);
            }
        }

        return [
            'enabled' => true,
            'batch_name' => $batch->name,
            'present' => $present,
            'absent' => $absent,
            'counts' => [
                'total' => count($present) + count($absent),
                'present' => count($present),
                'absent' => count($absent),
            ],
        ];
    }

    /**
     * @return array{roll: ?string, row: ?array<string, mixed>}
     */
    public function findByQuickSearch(string $term, string $date, ?int $batchId = null): array
    {
        $term = trim($term);

        if ($term === '') {
            return ['roll' => null, 'row' => null];
        }

        if (preg_match('/^[6-9]\d{9}$/', $term)) {
            $roll = $this->findRollByMobile($term, $batchId);

            if ($roll) {
                return [
                    'roll' => $roll,
                    'row' => $this->dashboard->studentDayRow($roll, $date, $this->logs->findStudentByRoll($roll)),
                ];
            }
        }

        $roll = $this->logs->normalizeRoll($term);
        $student = $this->logs->findStudentByRoll($roll);

        if ($student) {
            return [
                'roll' => $roll,
                'row' => $this->dashboard->studentDayRow($roll, $date, $student),
            ];
        }

        foreach ($this->dashboard->dashboardForDate($date, $batchId)['rows'] as $row) {
            if (str_contains(strtolower($row['student_name']), strtolower($term))) {
                return ['roll' => $row['roll'], 'row' => $row];
            }
        }

        return ['roll' => null, 'row' => null];
    }

    private function findRollByMobile(string $mobile, ?int $batchId): ?string
    {
        $query = Enrollment::query()
            ->where('is_active', true)
            ->whereHas('student', fn ($q) => $q->where('mobile', $mobile));

        if ($batchId) {
            $query->whereHas(
                'student.activeBatchStudent',
                fn ($q) => $q->where('batch_id', $batchId)->where('is_active', true),
            );
        }

        $roll = $query->value('enrollment_number');

        return $roll ? $this->logs->normalizeRoll((string) $roll) : null;
    }

    private function rollForStudent(Student $student): ?string
    {
        $roll = $student->activeEnrollment?->enrollment_number;

        return filled($roll) ? $this->logs->normalizeRoll((string) $roll) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function absentRow(Student $student, ?string $roll): array
    {
        return [
            'roll' => $roll,
            'student_id' => $student->id,
            'student_name' => $student->name,
            'mobile' => $student->mobile,
            'profile_url' => StudentProfilePage::getUrl(['record' => $student->id]),
        ];
    }

    /**
     * @return array{enabled: bool, batch_name: null, present: array, absent: array, counts: array{total: int, present: int, absent: int}}
     */
    private function emptyRoster(): array
    {
        return [
            'enabled' => false,
            'batch_name' => null,
            'present' => [],
            'absent' => [],
            'counts' => ['total' => 0, 'present' => 0, 'absent' => 0],
        ];
    }
}
