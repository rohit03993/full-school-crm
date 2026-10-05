<?php

namespace App\Services;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\StaffJobRole;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TeacherHomeworkScoreService
{
    public const GIVEN_WEIGHT = 50;

    public const CHECKED_WEIGHT = 50;

    /**
     * @return array{0: string, 1: string}
     */
    public function defaultRange(): array
    {
        return [
            now()->subDays(6)->toDateString(),
            now()->toDateString(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function normalizeRange(?string $from, ?string $to): array
    {
        [$defaultFrom, $defaultTo] = $this->defaultRange();
        $today = now()->toDateString();

        try {
            $end = filled($to) ? Carbon::parse($to)->toDateString() : $defaultTo;
        } catch (\Throwable) {
            $end = $defaultTo;
        }

        try {
            $start = filled($from) ? Carbon::parse($from)->toDateString() : $defaultFrom;
        } catch (\Throwable) {
            $start = $defaultFrom;
        }

        if ($end > $today) {
            $end = $today;
        }

        if ($start > $end) {
            $start = $end;
        }

        $earliest = Carbon::parse($end)->subDays(91)->toDateString();

        if ($start < $earliest) {
            $start = $earliest;
        }

        return [$start, $end];
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     period_label: string,
     *     teachers: list<array<string, mixed>>
     * }
     */
    public function report(?string $from, ?string $to, ?int $onlyUserId = null, bool $withLines = false): array
    {
        [$start, $end] = $this->normalizeRange($from, $to);
        $teachers = $this->teachers($onlyUserId);
        $context = $this->loadContext($teachers, $start, $end);
        $rows = [];

        foreach ($teachers as $teacher) {
            $rows[] = $this->scoreTeacher($teacher, $context, $withLines);
        }

        usort($rows, function (array $left, array $right): int {
            $leftScore = $left['score'];
            $rightScore = $right['score'];

            if ($leftScore === null && $rightScore === null) {
                return strnatcasecmp((string) $left['name'], (string) $right['name']);
            }

            if ($leftScore === null) {
                return 1;
            }

            if ($rightScore === null) {
                return -1;
            }

            if ($leftScore !== $rightScore) {
                return $leftScore <=> $rightScore;
            }

            return strnatcasecmp((string) $left['name'], (string) $right['name']);
        });

        return [
            'from' => $start,
            'to' => $end,
            'period_label' => Carbon::parse($start)->format('j M Y').' – '.Carbon::parse($end)->format('j M Y'),
            'teachers' => $rows,
        ];
    }

    /**
     * @param  Collection<int, User>  $teachers
     * @return array<string, mixed>
     */
    protected function loadContext(Collection $teachers, string $from, string $to): array
    {
        $teacherIds = $teachers->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $staffRows = $teacherIds === []
            ? collect()
            : BatchStaffAssignment::query()
                ->with(['batch.course', 'courseSubject'])
                ->where('role', BatchStaffRole::SubjectTeacher)
                ->whereNotNull('course_subject_id')
                ->whereIn('user_id', $teacherIds)
                ->whereHas('batch', fn ($query) => $query->where('status', BatchStatus::Active))
                ->get();

        $batchIds = $staffRows->pluck('batch_id')->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();

        $homework = $batchIds === []
            ? collect()
            : HomeworkAssignment::query()
                ->with(['courseSubject', 'submittedBy', 'createdBy'])
                ->whereIn('batch_id', $batchIds)
                ->whereNotNull('course_subject_id')
                ->whereDate('homework_date', '>=', $from)
                ->whereDate('homework_date', '<=', $to)
                ->whereIn('status', [
                    HomeworkAssignmentStatus::Submitted,
                    HomeworkAssignmentStatus::Approved,
                    HomeworkAssignmentStatus::Sent,
                ])
                ->orderBy('id')
                ->get();

        $roster = [];

        if ($batchIds !== []) {
            $studentRows = BatchStudent::query()
                ->where('is_active', true)
                ->whereIn('batch_id', $batchIds)
                ->whereHas('student')
                ->get(['batch_id', 'student_id']);

            foreach ($studentRows as $row) {
                $roster[(int) $row->batch_id][(int) $row->student_id] = true;
            }
        }

        $marked = [];
        $assignmentIds = $homework->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($assignmentIds !== []) {
            $checks = HomeworkCheck::query()
                ->whereIn('homework_assignment_id', $assignmentIds)
                ->orderByDesc('id')
                ->get(['homework_assignment_id', 'student_id', 'batch_id', 'status']);

            foreach ($checks as $check) {
                $assignmentId = (int) $check->homework_assignment_id;
                $studentId = (int) $check->student_id;
                $batchId = (int) $check->batch_id;

                if (! isset($roster[$batchId][$studentId])) {
                    continue;
                }

                if (isset($marked[$assignmentId][$studentId])) {
                    continue;
                }

                $marked[$assignmentId][$studentId] = $check->status;
            }
        }

        $homeworkDays = [];
        $owned = [];

        foreach ($homework as $assignment) {
            $batchId = (int) $assignment->batch_id;
            $subjectId = (int) $assignment->course_subject_id;
            $date = $assignment->homework_date?->toDateString();
            $ownerId = (int) ($assignment->submitted_by_user_id ?: $assignment->created_by_user_id);

            if ($date === null || $ownerId < 1) {
                continue;
            }

            $homeworkDays[$batchId.'|'.$date] = true;
            $owned[$ownerId.'|'.$batchId.'|'.$subjectId.'|'.$date] = $assignment;
        }

        return [
            'staff' => $staffRows->groupBy(fn (BatchStaffAssignment $row): int => (int) $row->user_id),
            'homework_days' => $homeworkDays,
            'owned' => $owned,
            'roster' => $roster,
            'marked' => $marked,
            'dates' => $this->datesBetween($from, $to),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function scoreTeacher(User $teacher, array $context, bool $withLines): array
    {
        $today = now()->toDateString();
        $given = 0;
        $missed = 0;
        $waiting = 0;
        $ready = 0;
        $readyStudents = 0;
        $markedStudents = 0;
        $classLabels = [];
        $lines = [];
        /** @var Collection<int, BatchStaffAssignment> $subjects */
        $subjects = $context['staff']->get((int) $teacher->id, collect());

        foreach ($subjects as $slot) {
            $batch = $slot->batch;
            $subject = $slot->courseSubject;

            if (! $batch || ! $subject) {
                continue;
            }

            $classLabel = $batch->displayLabel().' · '.$subject->name;
            $classLabels[$classLabel] = $classLabel;

            foreach ($context['dates'] as $date) {
                $dayKey = (int) $batch->id.'|'.$date;

                if (! isset($context['homework_days'][$dayKey])) {
                    continue;
                }

                $ownedKey = (int) $teacher->id.'|'.(int) $batch->id.'|'.(int) $subject->id.'|'.$date;
                /** @var HomeworkAssignment|null $assignment */
                $assignment = $context['owned'][$ownedKey] ?? null;

                if ($assignment === null) {
                    $missed++;

                    if ($withLines) {
                        $lines[] = [
                            'sort' => $date,
                            'date' => Carbon::parse($date)->format('j M Y'),
                            'class' => $batch->displayLabel(),
                            'label' => $subject->name.' ('.$teacher->name.')',
                            'state' => 'Missed',
                            'note' => null,
                            'counts_open' => false,
                            'done' => 0,
                            'not_done' => 0,
                            'unmarked' => null,
                        ];
                    }

                    continue;
                }

                $given++;
                $isWaiting = $assignment->status === HomeworkAssignmentStatus::Submitted;
                $isReady = in_array($assignment->status, [
                    HomeworkAssignmentStatus::Approved,
                    HomeworkAssignmentStatus::Sent,
                ], true) && $date < $today;
                $opensTomorrow = ! $isWaiting && $date >= $today;
                $studentTotal = count($context['roster'][(int) $batch->id] ?? []);
                $done = 0;
                $notDone = 0;

                if ($isReady) {
                    foreach ($context['marked'][(int) $assignment->id] ?? [] as $status) {
                        if ($status === HomeworkCheckStatus::Done) {
                            $done++;
                        } elseif ($status === HomeworkCheckStatus::NotDone) {
                            $notDone++;
                        }
                    }
                }

                $marked = min($done + $notDone, $studentTotal);

                if ($isWaiting) {
                    $waiting++;
                }

                if ($isReady && $studentTotal > 0) {
                    $ready++;
                    $readyStudents += $studentTotal;
                    $markedStudents += $marked;
                }

                if ($withLines) {
                    $lines[] = [
                        'sort' => $date,
                        'date' => Carbon::parse($date)->format('j M Y'),
                        'class' => $batch->displayLabel(),
                        'label' => $assignment->teacherSubjectLabel(),
                        'state' => 'Given',
                        'note' => $isWaiting
                            ? 'Waiting for approval'
                            : ($opensTomorrow ? 'Check opens tomorrow' : null),
                        'counts_open' => $isReady && $studentTotal > 0,
                        'done' => $done,
                        'not_done' => $notDone,
                        'unmarked' => $isReady ? max(0, $studentTotal - $marked) : null,
                    ];
                }
            }
        }

        usort($lines, function (array $left, array $right): int {
            return strcmp((string) $right['sort'], (string) $left['sort']);
        });

        $parts = $this->scoreParts($given, $given + $missed, $markedStudents, $readyStudents);
        $weight = 0;
        $points = 0.0;

        foreach ($parts as $part) {
            if (! $part['applicable']) {
                continue;
            }

            $weight += (int) $part['weight'];
            $points += (float) $part['score'];
        }

        $note = null;

        if ($classLabels === []) {
            $note = 'No subject assigned';
        } elseif ($weight === 0) {
            $note = 'No homework day in this period';
        }

        return [
            'user_id' => (int) $teacher->id,
            'name' => (string) $teacher->name,
            'classes' => implode(', ', array_values($classLabels)),
            'given' => $given,
            'missed' => $missed,
            'expected' => $given + $missed,
            'waiting' => $waiting,
            'ready' => $ready,
            'checked_students' => $markedStudents,
            'ready_students' => $readyStudents,
            'score' => $weight > 0 ? (int) round($points / $weight * 100) : null,
            'note' => $note,
            'parts' => $parts,
            'lines' => $lines,
        ];
    }

    /**
     * @return list<array{key: string, label: string, weight: int, applicable: bool, score: float, summary: string}>
     */
    protected function scoreParts(
        int $given,
        int $expected,
        int $markedStudents,
        int $readyStudents,
    ): array {
        $givenApplicable = $expected > 0;
        $checkedApplicable = $readyStudents > 0;

        return [
            [
                'key' => 'given',
                'label' => 'Homework given',
                'weight' => self::GIVEN_WEIGHT,
                'applicable' => $givenApplicable,
                'score' => $givenApplicable ? round($given / $expected * self::GIVEN_WEIGHT, 1) : 0,
                'summary' => $givenApplicable ? $given.' of '.$expected : 'No homework day',
            ],
            [
                'key' => 'checked',
                'label' => 'Homework checked',
                'weight' => self::CHECKED_WEIGHT,
                'applicable' => $checkedApplicable,
                'score' => $checkedApplicable ? round($markedStudents / $readyStudents * self::CHECKED_WEIGHT, 1) : 0,
                'summary' => $checkedApplicable
                    ? $markedStudents.' of '.$readyStudents.' students'
                    : ($given > 0 ? 'Not in the score yet' : 'No homework to check'),
            ],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    protected function teachers(?int $onlyUserId): Collection
    {
        $query = User::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($onlyUserId) {
            return $query->whereKey($onlyUserId)->get();
        }

        return $query
            ->whereHas('roles', fn ($roles) => $roles->where('name', StaffJobRole::Teacher->value))
            ->get();
    }

    /**
     * @return list<string>
     */
    protected function datesBetween(string $from, string $to): array
    {
        $dates = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
