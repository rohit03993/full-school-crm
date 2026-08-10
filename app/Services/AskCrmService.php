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
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AskCrmService
{
    public function __construct(
        protected StudentSearchService $students,
        protected AttendanceService $attendance,
        protected HomeworkCheckService $homeworkChecks,
        protected AskCrmGeminiService $gemini,
        protected AskCrmStudentDataService $studentData,
    ) {}

    /**
     * @param  list<array{role: string, text: string}>  $history
     * @return array{reply: string, intent: string, student_id: ?int}
     */
    public function ask(
        User $user,
        string $question,
        array $history = [],
        ?int $contextStudentId = null,
    ): array {
        $question = trim(preg_replace('/\s+/', ' ', $question) ?? '');

        if ($question === '') {
            return $this->result(
                AskCrmIntent::Help,
                'Please type a question — for example: “What is Ayyush’s attendance today?”',
            );
        }

        $contextStudent = $contextStudentId
            ? Student::query()->find($contextStudentId)
            : null;

        [$intent, $name, $useContextStudent] = $this->resolveIntentAndName(
            $question,
            $history,
            $contextStudentId,
            $contextStudent?->name,
        );

        $referencedDate = $this->extractReferencedDate($question);
        $isFollowUp = $this->isLikelyFollowUp($question) || filled($referencedDate);

        if ($intent === AskCrmIntent::Help) {
            return $this->result($intent, $this->helpReply());
        }

        if ($intent === AskCrmIntent::Unknown && $contextStudent && ($useContextStudent || $isFollowUp)) {
            $intent = $this->inferIntentFromHistory($history) ?? AskCrmIntent::StudentProfile;
            $useContextStudent = true;
        }

        if ($intent === AskCrmIntent::Unknown && $this->gemini->isEnabled() && $contextStudent && filled($name) === false && $isFollowUp) {
            $intent = AskCrmIntent::StudentProfile;
            $useContextStudent = true;
        }

        if ($intent === AskCrmIntent::Unknown && $this->canResolveStudent($name, $useContextStudent, $contextStudent, $question, $isFollowUp)) {
            $intent = $this->inferIntentFromQuestion($question) ?? AskCrmIntent::StudentProfile;
        }

        if ($intent === AskCrmIntent::Unknown) {
            return $this->result(
                $intent,
                "I’m not sure I understood that yet.\n\n".$this->helpReply(),
            );
        }

        $student = null;

        if ($useContextStudent && $contextStudent) {
            $student = $contextStudent;
        } elseif (filled($name)) {
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

            $student = $resolved['student'];
        } elseif ($contextStudent && $isFollowUp) {
            $student = $contextStudent;
        } else {
            return $this->result(
                $intent,
                'Please include the student name — for example: “tell me attendance of Ayyush”.',
            );
        }

        /** @var Student $student */
        $student = $student->loadMissing([
            'activeEnrollment.feeStructure',
            'activeBatchStudent.batch',
        ]);

        $snapshot = $this->studentData->snapshot($user, $student, $referencedDate);

        if ($this->gemini->isEnabled()) {
            $composed = $this->gemini->composeReply($question, $history, $snapshot);

            if (filled($composed)) {
                return $this->result($intent, $composed, (int) $student->id);
            }
        }

        if (filled($referencedDate)) {
            $dateReply = $this->dateSpecificReply($user, $student, $intent, $referencedDate, $snapshot);

            if (filled($dateReply)) {
                return $this->result($intent, $dateReply, (int) $student->id);
            }
        }

        $reply = match ($intent) {
            AskCrmIntent::AttendanceToday => $this->attendanceTodayReply($student),
            AskCrmIntent::AttendanceMonth => $this->attendanceMonthReply($student),
            AskCrmIntent::FeePending => $this->isInstallmentQuestion($question)
                ? $this->feeInstallmentsReply($user, $student, $snapshot)
                : $this->feePendingReply($user, $student),
            AskCrmIntent::HomeworkWeek => $this->homeworkWeekReply($student),
            AskCrmIntent::StudentProfile => $this->isInstallmentQuestion($question)
                ? $this->feeInstallmentsReply($user, $student, $snapshot)
                : $this->profileOverviewReply($user, $student, $snapshot),
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
     * @param  list<array{role: string, text: string}>  $history
     * @return array{0: AskCrmIntent, 1: ?string, 2: bool}
     */
    protected function resolveIntentAndName(
        string $question,
        array $history = [],
        ?int $contextStudentId = null,
        ?string $contextStudentName = null,
    ): array {
        $useContextStudent = false;

        $aiParsed = $this->gemini->parseQuestion(
            $question,
            $history,
            $contextStudentId,
            $contextStudentName,
        );

        $intent = $aiParsed['intent'] ?? null;
        $name = $aiParsed['student_name'] ?? null;
        $useContextStudent = (bool) ($aiParsed['use_context_student'] ?? false);

        if ($intent === null || $intent === AskCrmIntent::Unknown) {
            $ruleIntent = $this->detectIntent($question);

            if ($intent === null || ($intent === AskCrmIntent::Unknown && $ruleIntent !== AskCrmIntent::Unknown)) {
                $intent = $ruleIntent;
            }
        }

        if ($contextStudentId && $this->isLikelyFollowUp($question)) {
            $useContextStudent = true;

            if ($intent === AskCrmIntent::Unknown) {
                $intent = $this->inferIntentFromHistory($history) ?? AskCrmIntent::Unknown;
            }
        }

        if (! $useContextStudent && ! filled($name)) {
            $name = $this->extractStudentName($question);
        }

        return [$intent ?? AskCrmIntent::Unknown, $name, $useContextStudent];
    }

    /**
     * @param  list<array{role: string, text: string}>  $history
     */
    protected function inferIntentFromHistory(array $history): ?AskCrmIntent
    {
        $recent = array_slice(array_reverse($history), 0, 6);
        $blob = mb_strtolower(implode(' ', array_map(
            fn (array $item): string => (string) ($item['text'] ?? ''),
            $recent,
        )));

        if ($this->containsAny(' '.$blob.' ', ['homework', 'home work', 'not done', 'done or not', 'assignment'])) {
            return AskCrmIntent::HomeworkWeek;
        }

        if ($this->containsAny(' '.$blob.' ', ['receipt', 'payment', 'paid on'])) {
            return AskCrmIntent::FeePending;
        }

        if ($this->containsAny(' '.$blob.' ', ['call', 'called', 'telecall'])) {
            return AskCrmIntent::StudentProfile;
        }

        if ($this->containsAny(' '.$blob.' ', ['visit', 'campus', 'walk in'])) {
            return AskCrmIntent::StudentProfile;
        }

        if ($this->containsAny(' '.$blob.' ', ['exam', 'marks', 'test', 'mock'])) {
            return AskCrmIntent::StudentProfile;
        }

        if ($this->containsAny(' '.$blob.' ', ['case', 'complaint'])) {
            return AskCrmIntent::StudentProfile;
        }

        if ($this->containsAny(' '.$blob.' ', ['whatsapp', 'message', 'msg'])) {
            return AskCrmIntent::StudentProfile;
        }

        if ($this->containsAny(' '.$blob.' ', ['fee', 'fees', 'pending', 'balance', 'dues', 'bakaya', 'installment', 'tuition'])) {
            return AskCrmIntent::FeePending;
        }

        if ($this->containsAny(' '.$blob.' ', ['this month', 'monthly', 'percentage', 'percent'])) {
            return AskCrmIntent::AttendanceMonth;
        }

        if ($this->containsAny(' '.$blob.' ', ['attendance', 'attendence', 'present', 'absent', 'punch'])) {
            return AskCrmIntent::AttendanceToday;
        }

        return null;
    }

    protected function canResolveStudent(
        ?string $name,
        bool $useContextStudent,
        ?Student $contextStudent,
        string $question,
        bool $isFollowUp,
    ): bool {
        if ($useContextStudent && $contextStudent) {
            return true;
        }

        if (filled($name)) {
            return true;
        }

        if ($contextStudent && $isFollowUp) {
            return true;
        }

        return filled($this->extractStudentName($question));
    }

    protected function inferIntentFromQuestion(string $question): ?AskCrmIntent
    {
        $q = $this->normalizeQuestion($question);

        if ($this->isInstallmentQuestion($question)) {
            return AskCrmIntent::FeePending;
        }

        if ($this->containsAny($q, ['homework', 'home work', 'not done', 'assignment'])) {
            return AskCrmIntent::HomeworkWeek;
        }

        if ($this->containsAny($q, ['fee', 'fees', 'pending', 'balance', 'dues', 'bakaya', 'receipt', 'payment'])) {
            return AskCrmIntent::FeePending;
        }

        if ($this->containsAny($q, ['this month', 'monthly', 'percentage', 'percent'])) {
            return AskCrmIntent::AttendanceMonth;
        }

        if ($this->containsAny($q, ['attendance', 'attendence', 'present', 'absent', 'punch'])) {
            return AskCrmIntent::AttendanceToday;
        }

        if ($this->containsAny($q, ['call', 'called', 'visit', 'exam', 'marks', 'case', 'whatsapp', 'message'])) {
            return AskCrmIntent::StudentProfile;
        }

        return null;
    }

    protected function isInstallmentQuestion(string $question): bool
    {
        $q = $this->normalizeQuestion($question);

        return $this->containsAny($q, [
            'installment', 'installments', 'payment schedule', 'due date',
            'kitne installment', 'how many installment', 'fee plan', 'fee structure',
        ]);
    }

    protected function isLikelyFollowUp(string $question): bool
    {
        if (filled($this->extractStudentName($question))) {
            return false;
        }

        $q = $this->normalizeQuestion($question);

        if ($this->containsAny($q, [
            ' he ', ' she ', ' his ', ' her ', ' him ', ' has he', 'has she',
            'done or not', 'what is good', 'what about', 'tell me more', 'same student',
            'that mean', 'means', 'explain', 'clarify', ' uska ', ' uski ', ' uske ',
            'and on', 'on that day', 'that date', 'that day',
        ])) {
            return true;
        }

        if ($this->extractReferencedDate($question) !== null) {
            return true;
        }

        return (bool) preg_match('/\b(he|she|his|her|him|uska|uski|uske|kya|hai|hain)\b/u', $q);
    }

    public function extractReferencedDate(string $question): ?string
    {
        $question = trim($question);

        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $question, $matches)) {
            return $this->safeDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $question, $matches)) {
            return $this->safeDate((int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        if (preg_match('/\b(\d{1,2})\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{4})\b/i', $question, $matches)) {
            return $this->parseNaturalDate($matches[1].' '.$matches[2].' '.$matches[3]);
        }

        if (preg_match('/\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2}),?\s+(\d{4})\b/i', $question, $matches)) {
            return $this->parseNaturalDate($matches[1].' '.$matches[2].' '.$matches[3]);
        }

        return null;
    }

    protected function parseNaturalDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function safeDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function dateSpecificReply(User $user, Student $student, AskCrmIntent $intent, string $date, array $snapshot): ?string
    {
        $intro = $this->studentIntro($student);
        $formattedDate = Carbon::parse($date)->format('d M Y');

        if ($intent === AskCrmIntent::HomeworkWeek || $intent === AskCrmIntent::StudentProfile) {
            $homework = $snapshot['homework']['on_referenced_date'] ?? null;

            if (! is_array($homework)) {
                return null;
            }

            if (! ($homework['marked'] ?? false)) {
                return $intro.' has **no homework check recorded** for '.$formattedDate.'.';
            }

            $checks = collect($homework['checks'] ?? []);
            $done = (int) ($homework['done_count'] ?? 0);
            $notDone = (int) ($homework['not_done_count'] ?? 0);

            if ($notDone > 0 && $done === 0) {
                return $intro.' homework on '.$formattedDate.' was marked **Not Done** ('.$notDone.' subject'.($notDone === 1 ? '' : 's').').';
            }

            if ($done > 0 && $notDone === 0) {
                return $intro.' homework on '.$formattedDate.' was marked **Done** ('.$done.' subject'.($done === 1 ? '' : 's').').';
            }

            return $intro.' on '.$formattedDate.': **Done '.$done.'**, **Not Done '.$notDone.'**.';
        }

        if ($intent === AskCrmIntent::AttendanceToday || $intent === AskCrmIntent::AttendanceMonth || $intent === AskCrmIntent::StudentProfile) {
            $attendance = $snapshot['attendance']['on_referenced_date']['record'] ?? null;

            if (! is_array($attendance)) {
                return null;
            }

            $status = (string) ($attendance['status'] ?? 'not_marked');

            if ($status === 'not_marked') {
                return $intro.' has **no attendance mark** for '.$formattedDate.'.';
            }

            return $intro.' was **'.$status.'** on '.$formattedDate.'.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function profileOverviewReply(User $user, Student $student, array $snapshot): string
    {
        $intro = $this->studentIntro($student);
        $counters = collect($snapshot['profile_summary']['counters'] ?? [])
            ->map(fn (array $item): string => ($item['label'] ?? '').': '.($item['value'] ?? '—'))
            ->implode(' · ');

        return $intro."\n".$counters;
    }

    public function detectIntent(string $question): AskCrmIntent
    {
        $q = $this->normalizeQuestion($question);

        if ($this->containsAny($q, ['help', 'what can you', 'example', 'examples', 'how to ask', 'what do you do'])) {
            return AskCrmIntent::Help;
        }

        $mentionsFee = $this->containsAny($q, [
            'fee', 'fees', 'pending', 'balance', 'due', 'dues', 'outstanding', 'bakaya', 'baaki fee', 'kitna fee',
            'installment', 'installments', 'payment schedule', 'tuition', 'kitne installment',
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
        $original = preg_replace('/\s*(?:--|—|–)\s*/u', ' — ', $original) ?? $original;

        // 1–4 name tokens; do not start with common English filler words.
        $namePart = '(?!(?:what|whats|is|are|the|a|an|of|for|tell|me|how|much|show|give|get|please|today|this|month|fee|fees|homework|attendance|attendence|pending|balance|status|about|student|many|installment|installments|amount)\b)([A-Za-z][A-Za-z.\']+(?:\s+[A-Za-z][A-Za-z.\']+){0,3})';

        // "AARJAV JAIN — how many installments..."
        if (preg_match('/^'.$namePart.'\s*(?:—|:)\s*/ui', $original, $matches)) {
            $name = $this->cleanNameCandidate($matches[1]);
            if (filled($name)) {
                return $name;
            }
        }

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
            '/\b(what|whats|what\'s|is|are|the|a|an|of|for|today|this|month|monthly|percentage|percent|attendance|attendence|present|absent|fee|fees|pending|balance|due|dues|homework|home\s*work|not|done|week|how|much|many|tell|me|about|student|please|can|you|show|check|status|punch|give|get|batao|btado|sir|ji|now|update|kitna|kitne|good|or|has|he|she|his|her|him|mean|means|installment|installments|amount|have|it|and|what)\b/iu',
            ' ',
            $original,
        ) ?? '';
        $stripped = preg_replace('/[?.,!—–-]/', ' ', $stripped) ?? '';
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

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function feeInstallmentsReply(User $user, Student $student, array $snapshot): string
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return 'Fees is not enabled on this licence.';
        }

        $fees = $snapshot['fees'] ?? [];

        if (! ($fees['enabled'] ?? false)) {
            return 'Fees is not enabled on this licence.';
        }

        if (! ($fees['can_view'] ?? false)) {
            return 'You don’t have permission to view fee details. Ask an admin if you need access.';
        }

        if (! ($fees['has_fee_structure'] ?? false)) {
            return $this->studentIntro($student).' has no active fee structure on file.';
        }

        $installments = $fees['installments'] ?? [];
        $intro = $this->studentIntro($student);

        if ($installments === []) {
            $netFee = (float) ($fees['net_fee'] ?? 0);
            $pending = (float) ($fees['tuition_pending'] ?? 0);

            return $intro.' has **no installment schedule** — net tuition is **₹'.number_format($netFee, 2)
                .'** with **₹'.number_format($pending, 2).'** remaining.';
        }

        $count = (int) ($fees['installment_count'] ?? count($installments));
        $lines = collect($installments)->map(function (array $row): string {
            $label = (string) ($row['label'] ?? 'Installment');
            $amount = (float) ($row['amount'] ?? 0);
            $paid = (float) ($row['paid_amount'] ?? 0);
            $balance = (float) ($row['pending_amount'] ?? max(0, $amount - $paid));
            $due = filled($row['due_date'] ?? null) ? ' · due '.$row['due_date'] : '';
            $status = filled($row['status'] ?? null) ? ' · '.$row['status'] : '';

            return '• **'.$label.'**: ₹'.number_format($amount, 2)
                .$due
                .' (paid ₹'.number_format($paid, 2).', balance ₹'.number_format($balance, 2).')'
                .$status;
        })->implode("\n");

        return $intro.' has **'.$count.' installment'.($count === 1 ? '' : 's')."**:\n".$lines;
    }

    protected function homeworkWeekReply(Student $student): string
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return 'Homework is not enabled on this licence.';
        }

        $intro = $this->studentIntro($student);
        $snapshot = $this->studentData->homeworkSnapshot((int) $student->id);
        $today = $snapshot['today'] ?? [];
        $week = $snapshot['this_week'] ?? [];

        if (($today['unmarked_today'] ?? true) && ($today['marked_count'] ?? 0) === 0) {
            $weekNotDone = (int) ($week['not_done_count'] ?? 0);

            if ($weekNotDone > 0) {
                return $intro.' has **no homework marked for today yet**, but has **'.$weekNotDone.' Not Done** mark'.($weekNotDone === 1 ? '' : 's').' earlier this week.';
            }

            return $intro.' has **no homework marked for today yet** and **no Not Done marks this week**. Homework may not have been checked yet.';
        }

        $todayDone = (int) ($today['done_count'] ?? 0);
        $todayNotDone = (int) ($today['not_done_count'] ?? 0);

        if ($todayNotDone > 0) {
            return $intro.' has **Not Done** homework today ('.$todayNotDone.' mark'.($todayNotDone === 1 ? '' : 's').').';
        }

        if ($todayDone > 0) {
            return $intro.' has homework marked **Done** today ('.$todayDone.' subject'.($todayDone === 1 ? '' : 's').').';
        }

        $count = (int) ($week['not_done_count'] ?? 0);

        if ($count < 1) {
            return $intro.' has **no Not Done homework marks this week**.';
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
        return "Ask in normal language — I read your CRM data (same as the student profile).\n"
            ."Examples:\n"
            ."• tell me homework for Abhinav Singh\n"
            ."• what about 9 Aug 2026? (after asking about a student)\n"
            ."• how much fee pending for Ayyush\n"
            .'• Ayyush attendance this month';
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
