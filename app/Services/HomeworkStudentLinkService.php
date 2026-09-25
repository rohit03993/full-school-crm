<?php

namespace App\Services;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkStudentLink;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

    /**
     * @return array{opened: int, total: int, opened_people: list<array{name: string, at: ?string}>, not_opened_people: list<string>}
     */
    public function emptyStats(): array
    {
        return [
            'opened' => 0,
            'total' => 0,
            'opened_people' => [],
            'not_opened_people' => [],
        ];
    }

    /**
     * @param  Collection<int, mixed>  $assignmentIds
     * @return array<int, array{opened: int, total: int, opened_people: list<array{name: string, at: ?string}>, not_opened_people: list<string>}>
     */
    public function statsByAssignmentId(Collection $assignmentIds): array
    {
        $ids = $assignmentIds
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! Schema::hasTable('homework_student_links')) {
            return [];
        }

        $grouped = HomeworkStudentLink::query()
            ->whereIn('homework_assignment_id', $ids)
            ->with('student')
            ->get()
            ->groupBy('homework_assignment_id');

        $stats = [];

        foreach ($grouped as $assignmentId => $links) {
            $openedPeople = [];
            $notOpenedPeople = [];

            foreach ($links as $link) {
                $name = filled($link->student?->name) ? (string) $link->student->name : 'Student';
                $opened = $this->linkWasOpened($link);

                if ($opened) {
                    $openedPeople[] = [
                        'name' => $name,
                        'at' => $link->last_clicked_at?->timezone((string) config('app.timezone'))->format('h:i A'),
                    ];
                } else {
                    $notOpenedPeople[] = $name;
                }
            }

            usort($openedPeople, fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));
            usort($notOpenedPeople, fn (string $left, string $right): int => strnatcasecmp($left, $right));

            $stats[(int) $assignmentId] = [
                'opened' => count($openedPeople),
                'total' => $links->count(),
                'opened_people' => $openedPeople,
                'not_opened_people' => $notOpenedPeople,
            ];
        }

        return $stats;
    }

    /**
     * Unique-link open state for one class/subject/date. Empty when there is no unique send yet.
     *
     * @return array<int, array{opened: bool, at: ?string}>
     */
    public function openStateForClassSubjectDate(int $batchId, int $courseSubjectId, string $date): array
    {
        if ($batchId < 1 || $courseSubjectId < 1 || ! Schema::hasTable('homework_student_links')) {
            return [];
        }

        $assignment = HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->where('course_subject_id', $courseSubjectId)
            ->whereDate('homework_date', $date)
            ->first();

        if ($assignment === null) {
            return [];
        }

        return HomeworkStudentLink::query()
            ->where('homework_assignment_id', $assignment->id)
            ->get()
            ->mapWithKeys(fn (HomeworkStudentLink $link): array => [
                (int) $link->student_id => [
                    'opened' => $this->linkWasOpened($link),
                    'at' => $link->last_clicked_at?->timezone((string) config('app.timezone'))->format('h:i A'),
                ],
            ])
            ->all();
    }

    protected function linkWasOpened(HomeworkStudentLink $link): bool
    {
        return ((int) $link->click_count) > 0 || $link->first_clicked_at !== null;
    }
}
