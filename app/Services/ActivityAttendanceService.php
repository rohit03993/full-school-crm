<?php

namespace App\Services;

use App\Enums\ActivityKind;
use App\Models\ActivityAttendance;
use App\Models\BatchStudent;
use App\Models\IndustrialVisit;
use App\Models\PracticalSession;
use App\Models\Seminar;
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

    public function resolve(ActivityKind $kind, int $id): PracticalSession|IndustrialVisit|Seminar
    {
        $model = $kind->modelClass()::query()
            ->with('batch')
            ->findOrFail($id);

        return $model;
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
     * @param  array<int, bool|string>  $marks
     */
    public function saveMarks(Model $attendable, array $marks, User $staff): int
    {
        $batchId = $this->batchIdFor($attendable);

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

        DB::transaction(function () use ($attendable, $marks, $staff, $activeStudentIds, &$saved): void {
            foreach ($marks as $studentId => $isPresent) {
                $studentId = (int) $studentId;

                if (! in_array($studentId, $activeStudentIds, true)) {
                    continue;
                }

                ActivityAttendance::query()->updateOrCreate(
                    [
                        'attendable_type' => $attendable->getMorphClass(),
                        'attendable_id' => $attendable->getKey(),
                        'student_id' => $studentId,
                    ],
                    [
                        'is_present' => filter_var($isPresent, FILTER_VALIDATE_BOOLEAN),
                        'marked_by_user_id' => $staff->id,
                    ],
                );

                $saved++;
            }

            if ($saved > 0) {
                $kind = ActivityKind::tryFromModel($attendable);

                $this->audit->log(
                    'activity_attendance_marked',
                    $attendable,
                    null,
                    [
                        'activity_kind' => $kind?->value,
                        'records_saved' => $saved,
                    ],
                    user: $staff,
                );
            }
        });

        return $saved;
    }

    public function presentCountForStudent(Student $student, ActivityKind $kind): int
    {
        return ActivityAttendance::query()
            ->where('student_id', $student->id)
            ->where('is_present', true)
            ->where('attendable_type', $kind->modelClass())
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ActivityAttendance>
     */
    public function presentRecordsForStudent(Student $student, ActivityKind $kind)
    {
        return ActivityAttendance::query()
            ->where('student_id', $student->id)
            ->where('is_present', true)
            ->where('attendable_type', $kind->modelClass())
            ->with('attendable.batch')
            ->latest('updated_at')
            ->limit(100)
            ->get();
    }

    protected function batchIdFor(Model $attendable): int
    {
        return match (true) {
            $attendable instanceof PracticalSession => $attendable->batch_id,
            $attendable instanceof IndustrialVisit => $attendable->batch_id,
            $attendable instanceof Seminar => $attendable->batch_id,
            default => throw new \InvalidArgumentException('Unsupported activity model.'),
        };
    }
}
