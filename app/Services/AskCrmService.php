<?php

namespace App\Services;

use App\Enums\AskCrmIntent;
use App\Enums\AttendanceStatus;
use App\Enums\LicenseFeature;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Illuminate\Support\Collection;

class AskCrmService
{
    public function __construct(
        protected StudentSearchService $students,
        protected AttendanceService $attendance,
        protected HomeworkCheckService $homeworkChecks,
    ) {}

    /**
     * @return array{reply: string, intent: string, student_id: ?int}
     */
    public function ask(User $user, string $question): array
    {
        $question = trim(preg_replace('/\s+/', ' ', $question) ?? '');

        if ($question === '') {
            return $this->result(
                AskCrmIntent::Help,
                'Please type a question — for example: “What is Ayyush’s attendance today?”',
            );
        }

        $intent = $this->detectIntent($question);

        if ($intent === AskCrmIntent::Help) {
            return $this->result($intent, $this->helpReply());
        }

        if ($intent === AskCrmIntent::Unknown) {
            return $this->result(
                $intent,
                "I’m not sure I understood that yet.\n\n".$this->helpReply(),
            );
        }

        $name = $this->extractStudentName($question);

        if (! filled($name)) {
            return $this->result(
                $intent,
                'Please include the student name — for example: “What is Ayyush’s attendance today?”',
            );
        }

        $search = $this->students->search(null, $name);

        if ($search['outcome'] === StudentSearchService::OUTCOME_NOT_FOUND) {
            return $this->result(
                $intent,
                'I couldn’t find a student matching “'.$name.'”. Try a fuller name, or check spelling.',
            );
        }

        if ($search['outcome'] === StudentSearchService::OUTCOME_MULTIPLE) {
            /** @var Collection<int, Student> $matches */
            $matches = $search['students'];

            return $this->result(
                $intent,
                $this->multipleStudentsReply($name, $matches),
            );
        }

        /** @var Student $student */
        $student = $search['student']->loadMissing([
            'activeEnrollment.feeStructure',
            'activeBatchStudent.batch',
        ]);

        $reply = match ($intent) {
            AskCrmIntent::AttendanceToday => $this->attendanceTodayReply($student),
            AskCrmIntent::AttendanceMonth => $this->attendanceMonthReply($student),
            AskCrmIntent::FeePending => $this->feePendingReply($user, $student),
            AskCrmIntent::HomeworkWeek => $this->homeworkWeekReply($student),
            default => $this->helpReply(),
        };

        return $this->result($intent, $reply, (int) $student->id);
    }

    public function detectIntent(string $question): AskCrmIntent
    {
        $q = mb_strtolower($question);

        if (preg_match('/\b(help|what can you|examples?|how (do|to) ask)\b/u', $q)) {
            return AskCrmIntent::Help;
        }

        if (preg_match('/\b(fee|fees|pending|balance|due|dues|outstanding)\b/u', $q)) {
            return AskCrmIntent::FeePending;
        }

        if (preg_match('/\b(homework|home\s*work|\bhw\b|not\s*done)\b/u', $q)) {
            return AskCrmIntent::HomeworkWeek;
        }

        if (preg_match('/\b(percent|percentage|%|this month|month to date|mtd)\b/u', $q)
            && preg_match('/\b(attendance|present|absent)\b/u', $q)) {
            return AskCrmIntent::AttendanceMonth;
        }

        if (preg_match('/\b(attendance|present|absent|punch|check[\s-]?in)\b/u', $q)) {
            if (preg_match('/\b(month|percent|percentage|%)\b/u', $q)) {
                return AskCrmIntent::AttendanceMonth;
            }

            return AskCrmIntent::AttendanceToday;
        }

        return AskCrmIntent::Unknown;
    }

    public function extractStudentName(string $question): ?string
    {
        if (preg_match('/\b(?:of|for)\s+([A-Za-z][A-Za-z.\s\']{1,50}?)(?:\s+(?:today|this|for|in|on|please|\?)|$)/u', $question, $matches)) {
            $name = $this->cleanNameCandidate($matches[1]);

            if (filled($name)) {
                return $name;
            }
        }

        if (preg_match('/\b([A-Za-z][A-Za-z.\s\']{1,40}?)(?:[’\']s)\s+(?:attendance|fee|fees|homework|balance|pending)/ui', $question, $matches)) {
            $name = $this->cleanNameCandidate($matches[1]);

            if (filled($name)) {
                return $name;
            }
        }

        $stripped = preg_replace(
            '/\b(what|whats|what\'s|is|are|the|a|an|of|for|today|this|month|percentage|percent|attendance|present|absent|fee|fees|pending|balance|due|dues|homework|home\s*work|not|done|week|how|much|tell|me|about|student|please|can|you|show|check|status|punch)\b/iu',
            ' ',
            $question,
        ) ?? '';
        $stripped = preg_replace('/[?.,!]/', ' ', $stripped) ?? '';
        $name = $this->cleanNameCandidate($stripped);

        return filled($name) ? $name : null;
    }

    protected function cleanNameCandidate(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = trim($value, " \t\n\r\0\x0B.,'\"-");

        if ($value === '' || mb_strlen($value) < 2) {
            return null;
        }

        return $value;
    }

    protected function attendanceTodayReply(Student $student): string
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return 'Attendance is not enabled on this licence.';
        }

        $batch = $student->activeBatchStudent?->batch;
        $intro = $this->studentIntro($student);

        if (! $batch) {
            return $intro.' is not assigned to an active class, so I can’t check today’s attendance.';
        }

        $row = Attendance::query()
            ->where('student_id', $student->id)
            ->where('batch_id', $batch->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->first();

        if (! $row) {
            return $intro.' has **no attendance mark yet for today** in '.$batch->name.'.';
        }

        $status = $row->status instanceof AttendanceStatus
            ? $row->status->label()
            : (string) $row->status;

        $extra = '';

        if ($row->checked_in_at) {
            $extra .= ' Checked in at '.$row->checked_in_at->format('h:i A').'.';
        }

        if ($row->checked_out_at) {
            $extra .= ' Checked out at '.$row->checked_out_at->format('h:i A').'.';
        }

        return $intro.' is marked **'.$status.'** today in '.$batch->name.'.'.$extra;
    }

    protected function attendanceMonthReply(Student $student): string
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return 'Attendance is not enabled on this licence.';
        }

        $intro = $this->studentIntro($student);
        $summary = $this->attendance->monthToDateSummaryForStudent($student);

        if ($summary === null) {
            return $intro.' doesn’t have enough attendance data for this month yet (no active class or working days).';
        }

        return $intro.' has **'.$summary['percentage'].'%** attendance this month'
            .' ('.$summary['period_label'].'): '
            .$summary['credited_days'].' credited / '.$summary['expected_days'].' working days'
            .' — Present '.$summary['present_days']
            .', Leave '.$summary['leave_days']
            .', Absent '.$summary['absent_days'].'.';
    }

    protected function feePendingReply(User $user, Student $student): string
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return 'Fees is not enabled on this licence.';
        }

        if (! CrmAccess::canViewFees($user)) {
            return 'You don’t have permission to view fee balances. Ask an admin if you need access.';
        }

        $intro = $this->studentIntro($student);
        $fees = $student->activeEnrollment?->feeStructure;

        if (! $fees) {
            return $intro.' has no active fee structure on file.';
        }

        $tuitionPending = (float) $fees->pending_amount;
        $totalPending = (float) $fees->totalCollectiblePending();
        $paid = (float) $fees->paid_amount;

        $reply = $intro.' has **₹'.number_format($tuitionPending, 2).'** tuition pending'
            .' (paid so far: ₹'.number_format($paid, 2).').';

        if ($totalPending > $tuitionPending + 0.009) {
            $reply .= ' Including other collectible charges, total due is **₹'.number_format($totalPending, 2).'**.';
        } elseif ($tuitionPending <= 0.009) {
            $reply .= ' Looks clear on tuition — nice!';
        }

        return $reply;
    }

    protected function homeworkWeekReply(Student $student): string
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return 'Homework is not enabled on this licence.';
        }

        $intro = $this->studentIntro($student);
        $count = $this->homeworkChecks->notDoneCountThisWeek((int) $student->id);

        if ($count < 1) {
            return $intro.' has **no Not Done homework marks this week**. Looking good.';
        }

        return $intro.' has **'.$count.' Not Done** homework mark'.($count === 1 ? '' : 's').' this week.';
    }

    protected function studentIntro(Student $student): string
    {
        $bits = [$student->name];
        $batch = $student->activeBatchStudent?->batch?->name;
        $roll = $student->activeEnrollment?->enrollment_number;

        if (filled($batch)) {
            $bits[] = $batch;
        }

        if (filled($roll)) {
            $bits[] = 'Roll '.$roll;
        }

        if (count($bits) === 1) {
            return $bits[0];
        }

        return $bits[0].' ('.implode(' · ', array_slice($bits, 1)).')';
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    protected function multipleStudentsReply(string $name, Collection $students): string
    {
        $lines = $students->take(8)->map(function (Student $student): string {
            $batch = $student->activeBatchStudent?->batch?->name ?? 'No class';
            $roll = $student->activeEnrollment?->enrollment_number ?? '—';

            return '• '.$student->name.' — '.$batch.' · Roll '.$roll;
        })->implode("\n");

        return 'I found more than one student matching “'.$name.'”:'."\n".$lines
            ."\n\nAsk again with a fuller name, or add the class/roll — for example: “attendance of Ayyush Sharma today”.";
    }

    protected function helpReply(): string
    {
        return "I can answer from your CRM data. Try asking:\n"
            ."• What is Ayyush’s attendance today?\n"
            ."• What is Ayyush’s attendance this month?\n"
            ."• How much fee is pending for Ayyush?\n"
            .'• How many homework Not Done for Ayyush this week?';
    }

    /**
     * @return array{reply: string, intent: string, student_id: ?int}
     */
    protected function result(AskCrmIntent $intent, string $reply, ?int $studentId = null): array
    {
        return [
            'reply' => $reply,
            'intent' => $intent->value,
            'student_id' => $studentId,
        ];
    }
}
