<?php

namespace App\Services;

use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\BatchStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivityAttendanceService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function resolve(int $id): ActivitySession
    {
        return ActivitySession::query()
            ->with(['batch', 'activityType'])
            ->findOrFail($id);
    }

    /**
     * @return array<int, bool> student_id => is_present
     */
    public function marksFor(Model $attendable): array
    {
        return ActivityAttendance::query()
            ->where('attendable_type', $attendable->getMorphClass())
            ->where('attendable_id', $attendable->getKey())
            ->pluck('is_present', 'student_id')
            ->map(fn (bool $present): bool => $present)
            ->all();
    }

    /**
     * @return array<int, array{marks_obtained: ?float, grade: ?string, remarks: ?string}>
     */
    public function scoresFor(Model $attendable): array
    {
        return ActivityAttendance::query()
            ->where('attendable_type', $attendable->getMorphClass())
            ->where('attendable_id', $attendable->getKey())
            ->get(['student_id', 'marks_obtained', 'grade', 'remarks'])
            ->mapWithKeys(fn (ActivityAttendance $row): array => [
                $row->student_id => [
                    'marks_obtained' => $row->marks_obtained !== null ? (float) $row->marks_obtained : null,
                    'grade' => $row->grade,
                    'remarks' => $row->remarks,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, bool|string>  $marks
     * @param  array<int, array{marks_obtained?: mixed, grade?: mixed, remarks?: mixed}|float|string|null>  $scores
     */
    public function saveMarks(Model $attendable, array $marks, User $staff, array $scores = []): int
    {
        if (! $attendable instanceof ActivitySession) {
            throw new \InvalidArgumentException('Unsupported activity model.');
        }

        $batchId = $attendable->batch_id;
        $maxMarks = $this->maxMarksForSession($attendable);

        $activeStudentIds = BatchStudent::query()
            ->where('batch_id', $batchId)
            ->where('is_active', true)
            ->pluck('student_id')
            ->all();

        if ($activeStudentIds === []) {
            throw ValidationException::withMessages([
                'batch_id' => 'This batch has no active students.',
            ]);
        }

        $saved = 0;

        DB::transaction(function () use ($attendable, $marks, $scores, $staff, $activeStudentIds, $maxMarks, &$saved): void {
            foreach ($marks as $studentId => $isPresent) {
                $studentId = (int) $studentId;

                if (! in_array($studentId, $activeStudentIds, true)) {
                    continue;
                }

                $score = $this->normalizeScore($scores[$studentId] ?? null, $maxMarks);
                $isPresent = filter_var($isPresent, FILTER_VALIDATE_BOOLEAN);

                ActivityAttendance::query()->updateOrCreate(
                    [
                        'attendable_type' => $attendable->getMorphClass(),
                        'attendable_id' => $attendable->getKey(),
                        'student_id' => $studentId,
                    ],
                    [
                        'is_present' => $isPresent,
                        'marks_obtained' => $isPresent ? $score['marks_obtained'] : null,
                        'grade' => $isPresent ? $score['grade'] : null,
                        'remarks' => $isPresent ? $score['remarks'] : null,
                        'marked_by_user_id' => $staff->id,
                    ],
                );

                $saved++;
            }

            if ($saved > 0) {
                $this->audit->log(
                    'activity_attendance_marked',
                    $attendable,
                    null,
                    [
                        'activity_type_id' => $attendable->activity_type_id,
                        'records_saved' => $saved,
                    ],
                    user: $staff,
                );
            }
        });

        return $saved;
    }

    public function presentCountForStudent(Student $student, ActivityType|int $activityType): int
    {
        $activityTypeId = $activityType instanceof ActivityType
            ? $activityType->id
            : $activityType;

        return ActivityAttendance::query()
            ->where('student_id', $student->id)
            ->where('is_present', true)
            ->where('attendable_type', ActivitySession::class)
            ->whereHasMorph('attendable', [ActivitySession::class], function ($query) use ($activityTypeId): void {
                $query->where('activity_type_id', $activityTypeId);
            })
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ActivityAttendance>
     */
    public function presentRecordsForStudent(Student $student, ActivityType|int $activityType)
    {
        $activityTypeId = $activityType instanceof ActivityType
            ? $activityType->id
            : $activityType;

        return ActivityAttendance::query()
            ->where('student_id', $student->id)
            ->where('is_present', true)
            ->where('attendable_type', ActivitySession::class)
            ->whereHasMorph('attendable', [ActivitySession::class], function ($query) use ($activityTypeId): void {
                $query->where('activity_type_id', $activityTypeId);
            })
            ->with(['attendable.batch', 'attendable.activityType'])
            ->latest('updated_at')
            ->limit(100)
            ->get();
    }

    protected function maxMarksForSession(ActivitySession $session): ?float
    {
        $maxMarks = $session->metadataValue('max_marks');

        if ($maxMarks === null || $maxMarks === '') {
            return null;
        }

        return (float) $maxMarks;
    }

    /**
     * @param  array{marks_obtained?: mixed, grade?: mixed, remarks?: mixed}|float|string|null  $score
     * @return array{marks_obtained: ?float, grade: ?string, remarks: ?string}
     */
    protected function normalizeScore(mixed $score, ?float $maxMarks): array
    {
        if (is_numeric($score)) {
            $score = ['marks_obtained' => $score];
        }

        if (! is_array($score)) {
            return ['marks_obtained' => null, 'grade' => null, 'remarks' => null];
        }

        $marks = filled($score['marks_obtained'] ?? null) ? (float) $score['marks_obtained'] : null;

        if ($marks !== null && $maxMarks !== null && $marks > $maxMarks) {
            throw ValidationException::withMessages([
                'marks_obtained' => "Marks cannot exceed max marks ({$maxMarks}).",
            ]);
        }

        $grade = filled($score['grade'] ?? null) ? (string) $score['grade'] : null;
        $remarks = filled($score['remarks'] ?? null) ? (string) $score['remarks'] : null;

        return [
            'marks_obtained' => $marks,
            'grade' => $grade,
            'remarks' => $remarks,
        ];
    }
}
