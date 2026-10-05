<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\HomeworkCheckStatus;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use Carbon\Carbon;

class HomeworkCheckReportService
{
    /**
     * Every active class for one date.
     * Each saved homework is its own line, so two teachers on one subject stay apart.
     *
     * @return array{
     *     date: string,
     *     date_label: string,
     *     is_today: bool,
     *     classes: list<array{
     *         batch_id: int,
     *         label: string,
     *         lines: list<array{
     *             assignment_id: int,
     *             label: string,
     *             counts_open: bool,
     *             done: int,
     *             not_done: int,
     *             unmarked: int|null,
     *             note: string|null
     *         }>
     *     }>
     * }
     */
    public function forDate(?string $date): array
    {
        $day = $this->normalizeDate($date);
        $isToday = $day === now()->toDateString();

        $batches = Batch::query()
            ->with(['course'])
            ->where('status', BatchStatus::Active)
            ->get()
            ->sortBy(fn (Batch $batch): string => $batch->displayLabel(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $batchIds = $batches->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $rosterByBatch = $this->rosterIdsByBatch($batchIds);
        $assignments = $this->assignmentsForDate($batchIds, $day);
        $checksByAssignment = $isToday
            ? []
            : $this->latestChecksByAssignment(
                $assignments->flatten()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                $day,
                $rosterByBatch,
            );

        $classes = [];

        foreach ($batches as $batch) {
            $batchId = (int) $batch->id;
            $studentCount = count($rosterByBatch[$batchId] ?? []);
            $lines = [];

            foreach ($assignments->get($batchId, collect()) as $assignment) {
                $lines[] = $this->line($assignment, $studentCount, $isToday, $checksByAssignment);
            }

            $classes[] = [
                'batch_id' => $batchId,
                'label' => $batch->displayLabel(),
                'lines' => $lines,
            ];
        }

        return [
            'date' => $day,
            'date_label' => Carbon::parse($day)->format('j M Y'),
            'is_today' => $isToday,
            'classes' => $classes,
        ];
    }

    public function normalizeDate(?string $date): string
    {
        $today = now()->toDateString();

        if (! filled($date)) {
            return now()->subDay()->toDateString();
        }

        try {
            $day = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return now()->subDay()->toDateString();
        }

        return $day > $today ? $today : $day;
    }

    /**
     * @param  list<int>  $batchIds
     * @return array<int, array<int, true>>
     */
    protected function rosterIdsByBatch(array $batchIds): array
    {
        if ($batchIds === []) {
            return [];
        }

        $roster = [];

        $rows = BatchStudent::query()
            ->where('is_active', true)
            ->whereIn('batch_id', $batchIds)
            ->whereHas('student')
            ->get(['batch_id', 'student_id']);

        foreach ($rows as $row) {
            $roster[(int) $row->batch_id][(int) $row->student_id] = true;
        }

        return $roster;
    }

    /**
     * @param  list<int>  $batchIds
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, HomeworkAssignment>>
     */
    protected function assignmentsForDate(array $batchIds, string $day): \Illuminate\Support\Collection
    {
        if ($batchIds === []) {
            return collect();
        }

        return HomeworkAssignment::query()
            ->with(['courseSubject', 'submittedBy', 'createdBy'])
            ->whereDate('homework_date', $day)
            ->whereNotNull('course_subject_id')
            ->whereIn('batch_id', $batchIds)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (HomeworkAssignment $row): int => (int) $row->batch_id);
    }

    /**
     * @param  list<int>  $assignmentIds
     * @param  array<int, array<int, true>>  $rosterByBatch
     * @return array<int, array<int, HomeworkCheckStatus>>
     */
    protected function latestChecksByAssignment(array $assignmentIds, string $day, array $rosterByBatch): array
    {
        if ($assignmentIds === []) {
            return [];
        }

        $rows = HomeworkCheck::query()
            ->whereIn('homework_assignment_id', $assignmentIds)
            ->whereDate('checked_on', $day)
            ->orderByDesc('id')
            ->get(['homework_assignment_id', 'student_id', 'batch_id', 'status']);

        $map = [];

        foreach ($rows as $row) {
            $assignmentId = (int) $row->homework_assignment_id;
            $studentId = (int) $row->student_id;
            $batchId = (int) $row->batch_id;

            if (! isset($rosterByBatch[$batchId][$studentId])) {
                continue;
            }

            if (isset($map[$assignmentId][$studentId])) {
                continue;
            }

            $map[$assignmentId][$studentId] = $row->status;
        }

        return $map;
    }

    /**
     * @param  array<int, array<int, HomeworkCheckStatus>>  $checksByAssignment
     * @return array{
     *     assignment_id: int,
     *     label: string,
     *     counts_open: bool,
     *     done: int,
     *     not_done: int,
     *     unmarked: int|null,
     *     note: string|null
     * }
     */
    protected function line(
        HomeworkAssignment $assignment,
        int $studentCount,
        bool $isToday,
        array $checksByAssignment,
    ): array {
        $waiting = $assignment->status === HomeworkAssignmentStatus::Submitted;
        $countsOpen = ! $isToday
            && ! $waiting
            && in_array($assignment->status, [
                HomeworkAssignmentStatus::Approved,
                HomeworkAssignmentStatus::Sent,
            ], true);

        $done = 0;
        $notDone = 0;

        if ($countsOpen) {
            foreach ($checksByAssignment[(int) $assignment->id] ?? [] as $status) {
                if ($status === HomeworkCheckStatus::Done) {
                    $done++;
                } elseif ($status === HomeworkCheckStatus::NotDone) {
                    $notDone++;
                }
            }
        }

        $note = null;

        if ($waiting) {
            $note = 'Waiting for approval';
        } elseif ($isToday) {
            $note = 'Check opens tomorrow';
        }

        return [
            'assignment_id' => (int) $assignment->id,
            'label' => $assignment->teacherSubjectLabel(),
            'counts_open' => $countsOpen,
            'done' => $done,
            'not_done' => $notDone,
            'unmarked' => $countsOpen ? max(0, $studentCount - $done - $notDone) : null,
            'note' => $note,
        ];
    }
}
