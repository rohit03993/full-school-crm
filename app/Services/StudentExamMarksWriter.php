<?php

namespace App\Services;

use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\Student;
use App\Models\User;
use App\Support\PublishedResultsGate;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Validation\ValidationException;

class StudentExamMarksWriter
{
    public function __construct(
        protected ActivityAttendanceService $attendance,
        protected ActivityMarksWhatsAppService $marksWhatsApp,
    ) {}

    /**
     * Save one student's scores onto an exam that already exists for their class.
     *
     * @param  array<string, mixed>  $marksBySubject  subject label => marks (blank = absent)
     */
    public function saveForStudent(Student $student, string $groupKey, array $marksBySubject, User $staff): int
    {
        $groupKey = trim($groupKey);

        if ($groupKey === '') {
            throw ValidationException::withMessages([
                'exam' => 'Choose an exam first.',
            ]);
        }

        PublishedResultsGate::assertMarksEditableForGroupKey($groupKey);

        $sessions = $this->marksWhatsApp->sessionsForMarksKey($groupKey);

        if ($sessions->isEmpty()) {
            throw ValidationException::withMessages([
                'exam' => 'That exam was not found for this class.',
            ]);
        }

        $saved = 0;
        $updatedSessionIds = [];

        foreach ($this->sessionsBySubject($sessions) as $subject => $subjectSessions) {
            if (! array_key_exists($subject, $marksBySubject)) {
                continue;
            }

            $session = $this->sessionToUpdate($subjectSessions, $student);

            if (in_array($session->id, $updatedSessionIds, true)) {
                continue;
            }

            $raw = $marksBySubject[$subject];
            $hasMarks = filled($raw) || $raw === 0 || $raw === 0.0 || $raw === '0';
            $present = $hasMarks ? [$student->id => true] : [$student->id => false];
            $scores = $hasMarks
                ? [$student->id => ['marks_obtained' => $raw]]
                : [$student->id => ['marks_obtained' => null]];

            $saved += $this->attendance->saveMarks($session, $present, $staff, $scores);
            $updatedSessionIds[] = $session->id;
        }

        return $saved;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ActivitySession>  $sessions
     * @return array<string, list<ActivitySession>>
     */
    protected function sessionsBySubject($sessions): array
    {
        $grouped = [];

        foreach ($sessions as $session) {
            $subject = StudentExamMarksMatrix::subjectForSession($session);
            $grouped[$subject][] = $session;
        }

        return $grouped;
    }

    /**
     * @param  list<ActivitySession>  $sessions
     */
    protected function sessionToUpdate(array $sessions, Student $student): ActivitySession
    {
        $ids = array_map(fn (ActivitySession $session): int => $session->id, $sessions);

        $existing = ActivityAttendance::query()
            ->where('attendable_type', (new ActivitySession)->getMorphClass())
            ->whereIn('attendable_id', $ids)
            ->where('student_id', $student->id)
            ->whereNotNull('marks_obtained')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            foreach ($sessions as $session) {
                if ($session->id === (int) $existing->attendable_id) {
                    return $session;
                }
            }
        }

        return $sessions[0];
    }
}
