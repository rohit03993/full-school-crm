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
     * Save the class grid. Empty cells mark that student absent for the paper.
     *
     * @param  array<int|string, array<string, mixed>>  $marksByStudent  student_id => subject => marks
     */
    public function saveForGroup(string $groupKey, array $marksByStudent, User $staff): int
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

        foreach ($this->sessionsBySubject($sessions) as $subject => $subjectSessions) {
            $bySession = [];

            foreach ($marksByStudent as $studentId => $subjectMarks) {
                if (! is_array($subjectMarks) || ! array_key_exists($subject, $subjectMarks)) {
                    continue;
                }

                $student = new Student;
                $student->setAttribute('id', (int) $studentId);
                $session = $this->sessionToUpdate($subjectSessions, $student);
                $raw = $subjectMarks[$subject];
                $hasMarks = filled($raw) || $raw === 0 || $raw === 0.0 || $raw === '0';

                $bySession[$session->id]['session'] = $session;
                $bySession[$session->id]['present'][(int) $studentId] = $hasMarks;
                $bySession[$session->id]['scores'][(int) $studentId] = [
                    'marks_obtained' => $hasMarks ? $raw : null,
                ];
            }

            foreach ($bySession as $payload) {
                $saved += $this->attendance->saveMarks(
                    $payload['session'],
                    $payload['present'],
                    $staff,
                    $payload['scores'],
                );
            }
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
