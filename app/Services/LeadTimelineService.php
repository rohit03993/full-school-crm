<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentCall;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeadTimelineService
{
    /**
     * @return Collection<int, array{
     *     type: string,
     *     label: string,
     *     occurred_at: Carbon,
     *     summary: string,
     *     detail: ?string,
     *     staff_name: ?string,
     *     follow_up_at: ?Carbon,
     *     status_label: ?string,
     * }>
     */
    public function forStudent(Student $student, int $limit = 50): Collection
    {
        $sequenceMap = $this->visitSequenceMap($student);

        $visits = collect($student->visits()
            ->inPerson()
            ->with(['staff', 'enquiry.course'])
            ->get())
            ->map(function (Visit $visit) use ($sequenceMap): array {
                $sequence = $sequenceMap[$visit->id] ?? 1;

                return [
                    'type' => $visit->isCampusVisit() ? 'campus_visit' : 'visit',
                    'label' => $visit->isCampusVisit()
                        ? 'Campus visit'
                        : 'Visit #'.$sequence,
                    'occurred_at' => Carbon::parse($visit->visit_date)->startOfDay(),
                    'summary' => $visit->discussion_summary,
                    'detail' => $visit->remarks,
                    'staff_name' => $visit->staff?->name,
                    'follow_up_at' => $visit->next_follow_up_date
                        ? Carbon::parse($visit->next_follow_up_date)->startOfDay()
                        : null,
                    'status_label' => $visit->displayStatusLabel(),
                ];
            });

        $calls = collect($student->calls()
            ->whereNull('student_case_id')
            ->with(['staff', 'enquiry.course', 'studentCase'])
            ->get())
            ->map(fn (StudentCall $call): array => [
                'type' => 'call',
                'label' => $call->call_direction->label().' call',
                'occurred_at' => $call->called_at ?? now(),
                'summary' => filled($call->call_notes)
                    ? $call->call_notes
                    : $call->call_status->label(),
                'detail' => $this->callDetailLabel($call),
                'staff_name' => $call->staff?->name,
                'follow_up_at' => $call->next_followup_at,
                'status_label' => $call->call_status->label(),
            ]);

        return $visits
            ->merge($calls)
            ->sortByDesc(fn (array $item): int => $item['occurred_at']->timestamp)
            ->take($limit)
            ->values();
    }

    /**
     * @return array<int, int>
     */
    public function visitSequenceMap(Student $student): array
    {
        $orderedIds = $student->visits()
            ->inPerson()
            ->orderBy('visit_date')
            ->orderBy('id')
            ->pluck('id');

        $map = [];

        foreach ($orderedIds as $index => $id) {
            $map[(int) $id] = $index + 1;
        }

        return $map;
    }

    public function visitSequenceNumber(Student $student, Visit $visit): int
    {
        return $this->visitSequenceMap($student)[$visit->id] ?? 1;
    }

    protected function callDetailLabel(StudentCall $call): ?string
    {
        $parts = array_filter([
            $call->who_answered?->label(),
            $call->enquiry?->course?->name,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
