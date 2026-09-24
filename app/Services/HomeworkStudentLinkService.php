<?php

namespace App\Services;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkStudentLink;
use App\Models\Student;
use Illuminate\Support\Collection;

class HomeworkStudentLinkService
{
    /**
     * One unique /h/ code per student for this homework. Reuse on Resend.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, HomeworkStudentLink>
     */
    public function ensureForAssignment(HomeworkAssignment $assignment, Collection $students): Collection
    {
        $studentIds = $students
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        $existing = HomeworkStudentLink::query()
            ->where('homework_assignment_id', $assignment->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(fn (HomeworkStudentLink $link): int => (int) $link->student_id);

        foreach ($studentIds as $studentId) {
            if ($existing->has($studentId)) {
                continue;
            }

            $link = HomeworkStudentLink::query()->create([
                'homework_assignment_id' => $assignment->id,
                'student_id' => $studentId,
                'token' => HomeworkAssignment::generateShareToken(),
                'click_count' => 0,
            ]);

            $existing->put($studentId, $link);
        }

        return $existing;
    }

    /**
     * @param  Collection<int, HomeworkAssignment>  $assignments
     * @param  Collection<int, Student>  $students
     */
    public function ensureForAssignments(Collection $assignments, Collection $students): void
    {
        foreach ($assignments as $assignment) {
            $this->ensureForAssignment($assignment, $students);
        }
    }

    public function publicUrlFor(HomeworkAssignment $assignment, Student $student): string
    {
        $link = $this->ensureForAssignment($assignment, collect([$student]))->get($student->id);

        return $link instanceof HomeworkStudentLink
            ? $link->publicUrl()
            : $assignment->publicUrl();
    }
}
