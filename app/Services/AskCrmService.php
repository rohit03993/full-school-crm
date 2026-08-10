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
        protected AskCrmGeminiService $gemini,
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

        [$intent, $name] = $this->resolveIntentAndName($question);

        if ($intent === AskCrmIntent::Help) {
            return $this->result($intent, $this->helpReply());
        }

        if ($intent === AskCrmIntent::Unknown) {
            return $this->result(
                $intent,
                "I’m not sure I understood that yet.\n\n".$this->helpReply(),
            );
        }

        if (! filled($name)) {
            return $this->result(
                $intent,
                'Please include the student name — for example: “tell me attendance of Ayyush”.',
            );
        }

        $resolved = $this->resolveStudent($name);

        if ($resolved['outcome'] === StudentSearchService::OUTCOME_NOT_FOUND) {
            return $this->result(
                $intent,
                'I couldn’t find a student matching “'.$name.'”. Try a fuller name, or check spelling.',
            );
        }

        if ($resolved['outcome'] === StudentSearchService::OUTCOME_MULTIPLE) {
            return $this->result(
                $intent,
                $this->multipleStudentsReply($name, $resolved['students']),
            );
        }

        /** @var Student $student */
        $student = $resolved['student']->loadMissing([
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

    /**
     * Exact search first, then light fuzzy match for small spelling differences (aayush / ayyush).
     *
     * @return array{outcome: string, student: ?Student, students: Collection<int, Student>}
     */
    protected function resolveStudent(string $name): array
    {
        $search = $this->students->search(null, $name);

        if ($search['outcome'] !== StudentSearchService::OUTCOME_NOT_FOUND) {
            return $search;
        }

        $needle = mb_strtolower(trim($name));
        $collapsed = preg_replace('/(.)\1+/u', '$1', $needle) ?? $needle;
        $prefixes = array_values(array_unique(array_filter([
            mb_substr($needle, 0, 2),
            mb_substr($collapsed, 0, 2),
            mb_substr($needle, 0, 1),
        ], fn (string $prefix): bool => mb_strlen($prefix) >= 1)));

        if ($prefixes === []) {
            return $search;
        }

        $candidates = Student::query()
            ->with([
                'activeEnrollment',
                'activeBatchStudent.batch',
            ])
            ->where(function ($query) use ($prefixes): void {
                foreach ($prefixes as $prefix) {
                    $query->orWhereRaw('LOWER(name) LIKE ?', [$prefix.'%']);
                }
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        $scored = $candidates
            ->map(function (Student $student) use ($needle, $collapsed): ?array {
                $first = mb_strtolower((string) str($student->name)->before(' '));
                $full = mb_strtolower($student->name);
                $firstCollapsed = preg_replace('/(.)\1+/u', '$1', $first) ?? $first;
                $distance = min(
                    levenshtein($needle, $first),
                    levenshtein($collapsed, $first),
                    levenshtein($collapsed, $firstCollapsed),
                    levenshtein($needle, $full),
                );

                if ($distance > 2) {
                    return null;
                }

                return ['student' => $student, 'distance' => $distance];
            })
            ->filter()
            ->sortBy('distance')
            ->values();

        if ($scored->isEmpty()) {
            return $search;
        }

        $bestDistance = (int) $scored->first()['distance'];
        $best = $scored->where('distance', $bestDistance)->pluck('student')->values();

        if ($best->count() === 1) {
            return [
                'outcome' => StudentSearchService::OUTCOME_FOUND,
                'student' => $best->first(),
                'students' => new Collection,
            ];
        }

        return [
            'outcome' => StudentSearchService::OUTCOME_MULTIPLE,
            'student' => null,
            'students' => $best,
        ];
    }

    /**
     * @return array{0: AskCrmIntent, 1: ?string}
     */
    protected function resolveIntentAndName(string $question): array
    {
        $aiParsed = $this->gemini->parseQuestion($question);

        $intent = $aiParsed['intent'] ?? null;

        if ($intent === null || $intent === AskCrmIntent::Unknown) {
            $ruleIntent = $this->detectIntent($question);

            if ($intent === null || ($intent === AskCrmIntent::Unknown && $ruleIntent !== AskCrmIntent::Unknown)) {
                $intent = $ruleIntent;
            }
        }

        $name = $aiParsed['student_name'] ?? null;

        if (! filled($name)) {
            $name = $this->extractStudentName($question);
        }

        return [$intent ?? AskCrmIntent::Unknown, $name];
    }

    public function detectIntent(string $question): AskCrmIntent
    {
        $q = $this->normalizeQuestion($question);

        if ($this->containsAny($q, ['help', 'what can you', 'example', 'examples', 'how to ask', 'what do you do'])) {
            return AskCrmIntent::Help;
        }

        $mentionsFee = $this->containsAny($q, [
            'fee', 'fees', 'pending', 'balance', 'due', 'dues', 'outstanding', 'bakaya', 'baaki fee', 'kitna fee',
        ]);
        $mentionsHomework = $this->containsAny($q, [
            'homework', 'home work', ' hw ', 'not done', 'assignment', 'ghar ka kaam',
        ]) || preg_match('/\bhw\b/u', $q);
        $mentionsAttendance = $this->containsAny($q, [
            'attendance', 'attendence', 'present', 'absent', 'punch', 'check in', 'check-in', 'checked in',
            'aaya', 'ayi', 'kitne din', 'kitna attendance',
        ]);
        $mentionsMonth = $this->containsAny($q, [
            'percent', 'percentage', '%', 'this month', 'month', 'monthly', 'mtd', 'month to date',
        ]);

        // Prefer the most specific topic when several keywords appear.
        if ($mentionsFee && ! $mentionsAttendance && ! $mentionsHomework) {
            return AskCrmIntent::FeePending;
        }

        if ($mentionsHomework && ! $mentionsAttendance) {
            return AskCrmIntent::HomeworkWeek;
        }

        if ($mentionsFee && ($mentionsAttendance || $mentionsHomework)) {
            // "fee pending" usually wins when fee words are strong.
            if ($this->containsAny($q, ['fee', 'fees', 'balance', 'dues', 'bakaya'])) {
                return AskCrmIntent::FeePending;
            }
        }

        if ($mentionsAttendance) {
            return $mentionsMonth ? AskCrmIntent::AttendanceMonth : AskCrmIntent::AttendanceToday;
        }

        if ($mentionsFee) {
            return AskCrmIntent::FeePending;
        }

        if ($mentionsHomework) {
            return AskCrmIntent::HomeworkWeek;
        }

        // Soft fallback: "tell me about X" / "status of X" with a name → attendance today.
        if ($this->containsAny($q, ['tell me', 'show me', 'status', 'batao', 'btado', 'update'])
            && filled($this->extractStudentName($question))) {
            return AskCrmIntent::AttendanceToday;
        }

        return AskCrmIntent::Unknown;
    }

    public function extractStudentName(string $question): ?string
    {
        $original = trim($question);
        // 1–4 name tokens; do not start with common English filler words.
        $namePart = '(?!(?:what|whats|is|are|the|a|an|of|for|tell|me|how|much|show|give|get|please|today|this|month|fee|fees|homework|attendance|attendence|pending|balance|status|about|student)\b)([A-Za-z][A-Za-z.\']+(?:\s+[A-Za-z][A-Za-z.\']+){0,3})';

        // "attendance of Aayush" / "fees for Aayush Yadav"
        if (preg_match('/\b(?:of|for)\s+'.$namePart.'(?:\s+(?:today|this|month|week|please|now|sir|ji)|\s*[?.!]|$)/ui', $original, $matches)) {
            $name = $this->cleanNameCandidate($matches[1]);
            if (filled($name)) {
                return $name;
            }
        }

        // "Aayush's attendance" / "Aayush attendance"
        if (preg_match('/\b'.$namePart.'(?:[’\']s)?\s+(?:attendance|attendence|fee|fees|homework|balance|pending|status)\b/ui', $original, $matches)) {
            $name = $this->cleanNameCandidate($matches[1]);
            if (filled($name)) {
                return $name;
            }
        }

        // "attendance Aayush" / "fee pending Aayush Yadav"
        if (preg_match('/\b(?:attendance|attendence|fee|fees|homework|pending|balance)\s+'.$namePart.'(?:\s*[?.!]|$)/ui', $original, $matches)) {
            $name = $this->cleanNameCandidate($matches[1]);
            if (filled($name)) {
                return $name;
            }
        }

        $stripped = preg_replace(
            '/\b(what|whats|what\'s|is|are|the|a|an|of|for|today|this|month|monthly|percentage|percent|attendance|attendence|present|absent|fee|fees|pending|balance|due|dues|homework|home\s*work|not|done|week|how|much|tell|me|about|student|please|can|you|show|check|status|punch|give|get|batao|btado|sir|ji|now|update|kitna|kitne)\b/iu',
            ' ',
            $original,
        ) ?? '';
        $stripped = preg_replace('/[?.,!]/', ' ', $stripped) ?? '';
        $name = $this->cleanNameCandidate($stripped);

        return filled($name) ? $name : null;
    }

    protected function normalizeQuestion(string $question): string
    {
        $q = mb_strtolower(trim($question));
        $q = str_replace(['’', '`'], "'", $q);
        $q = preg_replace('/[?.,!;:]+/u', ' ', $q) ?? $q;
        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;

        return ' '.$q.' ';
    }

    /**
     * @param  list<string>  $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function cleanNameCandidate(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = trim($value, " \t\n\r\0\x0B.,'\"-");

        $value = preg_replace(
            '/\b(what|whats|is|are|the|a|an|of|for|tell|me|how|much|show|give|get|please|attendance|attendence|fee|fees|homework|pending|balance|today|this|month|week|status|about|student)\b/iu',
            ' ',
            $value,
        ) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

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
        return "Ask in normal language — I read your CRM data. Examples:\n"
            ."• tell me attendance of Ayyush\n"
            ."• Ayyush attendance this month?\n"
            ."• how much fee pending for Ayyush\n"
            .'• homework not done for Ayyush this week';
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
