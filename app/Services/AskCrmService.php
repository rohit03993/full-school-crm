<?php

namespace App\Services;

use App\Enums\AskCrmIntent;
use App\Enums\AttendanceStatus;
use App\Enums\HomeworkCheckStatus;
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
        protected AskCrmStaffAssistService $staffAssist,
    ) {}

    /**
     * @param  list<array{role: string, text: string}>  $history
     * @return array{reply: string, intent: string, student_id: ?int, student_name: ?string, clear_context: bool}
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

        if ($this->wantsNewStudentContext($question) && ! filled($this->extractStudentName($question))) {
            return $this->result(
                AskCrmIntent::Help,
                'Got it — I cleared the previous student. Ask about someone else, for example: “homework for Priya Sharma”.',
                clearContext: true,
            );
        }

        $contextStudentId = $contextStudentId ?? $this->inferStudentIdFromHistory($history);

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

        if ($contextStudent && ($this->refersToContextStudent($question) || $isFollowUp)) {
            $useContextStudent = true;
        }

        if ($contextStudent && $this->questionMentionsStudentName($question, $contextStudent->name)) {
            $useContextStudent = true;

            if (filled($name) && ! $this->nameLikelyMatchesStudent($name, $contextStudent->name)) {
                $name = null;
            }
        }

        if (! filled($name) || ! $this->isPlausiblePersonName($name)) {
            $name = null;
        }

        if (filled($name) && $contextStudent) {
            $resolved = $this->resolveStudent($name);

            if ($resolved['outcome'] === StudentSearchService::OUTCOME_FOUND
                && (int) $resolved['student']->id !== (int) $contextStudent->id) {
                $useContextStudent = false;
            }
        }

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
                $resolved = $this->disambiguateMultipleStudents($resolved, $question, $contextStudent);

                if ($resolved['outcome'] === StudentSearchService::OUTCOME_FOUND) {
                    $student = $resolved['student'];
                } else {
                    return $this->result(
                        $intent,
                        $this->multipleStudentsReply($name, $resolved['students']),
                    );
                }
            } else {
                $student = $resolved['student'];
            }
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

        if (filled($referencedDate)) {
            $dateReply = $this->dateSpecificReply($user, $student, $intent, $referencedDate, $snapshot);

            if (filled($dateReply)) {
                return $this->studentResult($user, $student, $intent, $dateReply, $snapshot, $question, $referencedDate);
            }
        }

        $reply = match ($intent) {
            AskCrmIntent::AttendanceToday => $this->attendanceTodayReply($student),
            AskCrmIntent::AttendanceMonth => $this->attendanceMonthReply($student),
            AskCrmIntent::FeePending => $this->isInstallmentQuestion($question)
                ? $this->feeInstallmentsReply($user, $student, $snapshot)
                : $this->feePendingReply($user, $student),
            AskCrmIntent::HomeworkWeek => $this->homeworkWeekReply($student, $snapshot),
            AskCrmIntent::StudentProfile => match (true) {
                $this->isCaseQuestion($question) => $this->casesOpenReply($user, $student, $snapshot),
                $this->isInstallmentQuestion($question) => $this->feeInstallmentsReply($user, $student, $snapshot),
                default => null,
            },
            default => null,
        };

        if (filled($reply)) {
            return $this->studentResult($user, $student, $intent, $reply, $snapshot, $question, $referencedDate);
        }

        if ($this->gemini->isEnabled()) {
            $composed = $this->gemini->composeReply($question, $history, $snapshot);

            if (filled($composed)) {
                return $this->studentResult($user, $student, $intent, $composed, $snapshot, $question, $referencedDate);
            }
        }

        $fallback = $intent === AskCrmIntent::StudentProfile
            ? $this->profileOverviewReply($user, $student, $snapshot)
            : $this->helpReply();

        return $this->studentResult($user, $student, $intent, $fallback, $snapshot, $question, $referencedDate);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{reply: string, intent: string, student_id: ?int, student_name: ?string, clear_context: bool}
     */
    protected function studentResult(
        User $user,
        Student $student,
        AskCrmIntent $intent,
        string $reply,
        array $snapshot,
        string $question,
        ?string $referencedDate = null,
    ): array {
        $reply = $this->staffAssist->enhance($user, $student, $intent, $reply, $snapshot, $question, $referencedDate);

        return $this->result($intent, $reply, (int) $student->id, $student->name);
    }

    /**
     * @param  list<array{role: string, text: string}>  $history
     */
    protected function inferStudentIdFromHistory(array $history): ?int
    {
        foreach (array_reverse($history) as $item) {
            if (($item['role'] ?? '') !== 'user') {
                continue;
            }

            $name = $this->extractStudentName((string) ($item['text'] ?? ''));

            if (! filled($name) || ! $this->isPlausiblePersonName($name)) {
                continue;
            }

            $resolved = $this->resolveStudent($name);

            if ($resolved['outcome'] === StudentSearchService::OUTCOME_FOUND) {
                return (int) $resolved['student']->id;
            }
        }

        return null;
    }

    protected function wantsNewStudentContext(string $question): bool
    {
        $q = $this->normalizeQuestion($question);

        return $this->containsAny($q, [
            ' someone else', ' another student', ' different student', ' new student',
            ' switch student', ' change student', ' other student', ' clear context',
            ' new person', ' kisi aur', ' dusra student', ' naya student',
        ]) || (bool) preg_match('/\b(ask|talk|tell)\s+(about|me)\s+(someone|another)\b/u', trim($question));
    }

    protected function refersToContextStudent(string $question): bool
    {
        $q = $this->normalizeQuestion($question);

        if ($this->containsAny($q, [
            ' this student', ' the student', ' that student', ' same student',
            ' for this student', ' about this student', ' this child', ' the child',
            ' for him', ' for her', ' about him', ' about her', ' uska ', ' uski ', ' uske ',
        ])) {
            return true;
        }

        return (bool) preg_match('/\b(this|that|same|the)\s+(student|child)\b/u', $q);
    }

    protected function isPlausiblePersonName(string $value): bool
    {
        $lower = mb_strtolower(trim($value));

        if ($lower === '' || mb_strlen($lower) < 2) {
            return false;
        }

        foreach ([
            'this student', 'the student', 'that student', 'same student',
            'someone else', 'another student', 'different student', 'other student',
            'cases open', 'open cases', 'open case', 'for this', 'for the', 'and cases',
            'how many', 'what amount', 'installment', 'installments',
            'homework', 'attendance', 'attendence', 'status', 'pending', 'balance',
            'fee', 'fees', 'present', 'absent', 'case', 'cases', 'done', 'not',
            'parent', 'whatsapp', 'message', 'copy',
        ] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return false;
            }
        }

        if (in_array($lower, [
            'student', 'cases', 'case', 'open', 'this', 'the', 'and', 'child',
            'homework', 'attendance', 'status', 'pending', 'balance', 'fee', 'fees',
            'present', 'absent', 'done', 'not', 'singh', 'kumar', 'sharma', 'gupta',
            'parent', 'whatsapp', 'message', 'copy',
        ], true)) {
            return false;
        }

        $tokens = $this->nameTokens($lower);

        foreach ($tokens as $token) {
            if (in_array($token, [
                'aaj', 'school', 'aaya', 'ayi', 'kya', 'hai', 'hain', 'batao', 'btado',
                'what', 'whats', 'how', 'tell', 'show', 'please', 'need', 'want', 'check',
                'homework', 'attendance', 'status', 'pending', 'balance', 'fee', 'fees',
                'present', 'absent', 'done', 'not', 'today', 'week', 'month', 'student',
            ], true)) {
                return false;
            }
        }

        if (count($tokens) === 1 && mb_strlen($tokens[0]) < 4) {
            return false;
        }

        return true;
    }

    protected function questionMentionsStudentName(string $question, string $studentName): bool
    {
        $question = mb_strtolower($question);
        $studentName = mb_strtolower(trim($studentName));

        if ($studentName === '') {
            return false;
        }

        if (str_contains($question, $studentName)) {
            return true;
        }

        $tokens = $this->nameTokens($studentName);

        if (count($tokens) < 2) {
            return false;
        }

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2 || ! str_contains($question, $token)) {
                return false;
            }
        }

        return true;
    }

    protected function nameLikelyMatchesStudent(string $candidate, string $studentName): bool
    {
        $candidate = mb_strtolower(trim($candidate));
        $studentName = mb_strtolower(trim($studentName));

        if ($candidate === '' || $studentName === '') {
            return false;
        }

        if ($candidate === $studentName || str_contains($studentName, $candidate) || str_contains($candidate, $studentName)) {
            return true;
        }

        $candidateTokens = $this->nameTokens($candidate);
        $studentTokens = $this->nameTokens($studentName);

        return count(array_intersect($candidateTokens, $studentTokens)) >= min(2, count($studentTokens));
    }

    protected function isBetterNameCandidate(?string $current, ?string $candidate): bool
    {
        if (! filled($candidate) || ! $this->isPlausiblePersonName($candidate)) {
            return false;
        }

        if (! filled($current)) {
            return true;
        }

        if (! $this->isPlausiblePersonName($current)) {
            return true;
        }

        $currentTokens = $this->nameTokens($current);
        $candidateTokens = $this->nameTokens($candidate);

        if (count($candidateTokens) > count($currentTokens)) {
            return true;
        }

        if (count($currentTokens) === 1 && count($candidateTokens) >= 2) {
            return true;
        }

        return mb_strlen($candidate) > mb_strlen($current) + 2;
    }

    /**
     * @return list<string>
     */
    protected function nameTokens(string $value): array
    {
        $value = mb_strtolower(trim($value));
        $parts = preg_split('/\s+/u', $value) ?: [];

        return array_values(array_filter($parts, fn (string $part): bool => mb_strlen($part) >= 2));
    }

    /**
     * @param  array{outcome: string, student: ?Student, students: Collection<int, Student>}  $resolved
     * @return array{outcome: string, student: ?Student, students: Collection<int, Student>}
     */
    protected function disambiguateMultipleStudents(array $resolved, string $question, ?Student $contextStudent): array
    {
        $students = $resolved['students'];

        if ($contextStudent && $this->questionMentionsStudentName($question, $contextStudent->name)) {
            $match = $students->firstWhere('id', $contextStudent->id);

            if ($match instanceof Student) {
                return [
                    'outcome' => StudentSearchService::OUTCOME_FOUND,
                    'student' => $match,
                    'students' => new Collection,
                ];
            }
        }

        $ruleName = $this->extractStudentName($question);

        if (filled($ruleName)) {
            $exact = $students->first(
                fn (Student $student): bool => mb_strtolower($student->name) === mb_strtolower($ruleName),
            );

            if ($exact instanceof Student) {
                return [
                    'outcome' => StudentSearchService::OUTCOME_FOUND,
                    'student' => $exact,
                    'students' => new Collection,
                ];
            }
        }

        return $resolved;
    }

    protected function isCaseQuestion(string $question): bool
    {
        $q = $this->normalizeQuestion($question);

        return $this->containsAny($q, [
            ' case', ' cases', 'open case', 'open cases', 'complaint', 'grievance',
        ]) || (bool) preg_match('/\bcases?\s+open\b/u', $q);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function casesOpenReply(User $user, Student $student, array $snapshot): string
    {
        $cases = $snapshot['cases'] ?? [];
        $intro = $this->studentIntro($student);

        if (! ($cases['enabled'] ?? false)) {
            return $intro.' — cases are only for enrolled students.';
        }

        if (($cases['can_view'] ?? true) === false) {
            return 'You don’t have permission to view student cases.';
        }

        $openCases = $cases['open_cases'] ?? [];

        if ($openCases === []) {
            return $intro.' has **no open cases** right now.';
        }

        $lines = collect($openCases)->map(function (array $case): string {
            return '• **'.$case['case_number'].'** — '.$case['title']
                .' ('.$case['type_label'].') · assignee: '.$case['assignee_name']
                .' · opened '.$case['opened_at_label'];
        })->implode("\n");

        $count = count($openCases);

        return $intro.' has **'.$count.' open case'.($count === 1 ? '' : 's')."**:\n".$lines;
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
        $name = filled($aiParsed['student_name'] ?? null)
            ? $this->cleanNameCandidate($this->stripDatePhrases((string) $aiParsed['student_name']))
            : null;
        $useContextStudent = (bool) ($aiParsed['use_context_student'] ?? false);
        $ruleName = $this->extractStudentName($question);

        if ($this->isBetterNameCandidate($name, $ruleName)) {
            $name = $ruleName;
        } elseif (! filled($name)) {
            $name = $ruleName;
        }

        if ($contextStudentId && $contextStudentName && filled($ruleName)
            && $this->nameLikelyMatchesStudent($ruleName, $contextStudentName)) {
            $useContextStudent = true;
        }

        if ($intent === null || $intent === AskCrmIntent::Unknown) {
            $ruleIntent = $this->detectIntent($question);

            if ($intent === null || ($intent === AskCrmIntent::Unknown && $ruleIntent !== AskCrmIntent::Unknown)) {
                $intent = $ruleIntent;
            }
        }

        if ($contextStudentId && ($this->refersToContextStudent($question) || $this->isLikelyFollowUp($question))) {
            $useContextStudent = true;

            if ($intent === AskCrmIntent::Unknown) {
                $intent = $this->inferIntentFromQuestion($question)
                    ?? $this->inferIntentFromHistory($history)
                    ?? AskCrmIntent::StudentProfile;
            }
        }

        if (! $useContextStudent && ! filled($name)) {
            $name = $this->extractStudentName($question);
        }

        if (filled($name) && ! $this->isPlausiblePersonName($name)) {
            $name = null;
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

        if ($this->containsAny($q, ['call', 'called', 'visit', 'exam', 'marks', 'whatsapp', 'message'])) {
            return AskCrmIntent::StudentProfile;
        }

        if ($this->isCaseQuestion($question)) {
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
        if ($this->refersToContextStudent($question)) {
            return true;
        }

        if (preg_match('/^\s*and\b/iu', trim($question))) {
            return true;
        }

        $name = $this->extractStudentName($question);

        if (filled($name) && $this->isPlausiblePersonName($name)) {
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
                $details = $this->homeworkCheckDetails($checks, HomeworkCheckStatus::NotDone->label());

                return $intro.' homework on '.$formattedDate.' was marked **Not Done**'
                    .($details !== '' ? ': '.$details.'.' : ' ('.$notDone.' subject'.($notDone === 1 ? '' : 's').').');
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

    protected function stripParentWhatsAppPhrases(string $question): string
    {
        $question = trim($question);

        foreach ([
            'whatsapp message for parent',
            'whatsapp for parent',
            'message for parent',
            'parent whatsapp',
            'whatsapp copy for parent',
            'whatsapp msg for parent',
            'parent message',
        ] as $phrase) {
            $question = (string) preg_replace('/\s*(?:—|--|–|-)?\s*'.preg_quote($phrase, '/').'\s*$/iu', '', $question);
        }

        return trim($question);
    }

    public function extractStudentName(string $question): ?string
    {
        $original = trim($question);
        $original = $this->stripParentWhatsAppPhrases($original);
        $original = preg_replace('/\s*(?:--|—|–)\s*/u', ' — ', $original) ?? $original;

        // 1–4 name tokens; do not start with common English filler words.
        $namePart = '(?!(?:what|whats|is|are|the|a|an|of|for|tell|me|how|much|show|give|get|please|today|this|month|fee|fees|homework|attendance|attendence|pending|balance|status|about|student|many|installment|installments|amount)\b)([A-Za-z][A-Za-z.\']+(?:\s+[A-Za-z][A-Za-z.\']+){0,3})';

        // "ABHINAV SINGH - homework for 9 aug 2026" / "AARJAV JAIN — how many installments..."
        if (preg_match('/^'.$namePart.'\s*(?:—|–|-|:)\s*/ui', $original, $matches)) {
            $name = $this->cleanNameCandidate($matches[1]);
            if (filled($name)) {
                return $name;
            }
        }

        // "ABHINAV SINGH what is the homework status"
        if (preg_match('/^'.$namePart.'\s+(?:what|whats|what\'s|how|tell|show|please|can|could|i|need|want|give|check|is|are)\b/ui', $original, $matches)) {
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
            '/\b(what|whats|what\'s|is|are|the|a|an|of|for|today|this|month|monthly|percentage|percent|attendance|attendence|present|absent|fee|fees|pending|balance|due|dues|homework|home\s*work|not|done|week|how|much|many|tell|me|about|student|please|can|you|show|check|status|punch|give|get|batao|btado|sir|ji|now|update|kitna|kitne|good|or|has|he|she|his|her|him|mean|means|installment|installments|amount|have|it|and|what|aaj|school|aaya|ayi|kya|hai|hain|parent|whatsapp|message|copy|send)\b/iu',
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

    protected function stripDatePhrases(string $text): string
    {
        $text = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', ' ', $text) ?? $text;
        $text = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}\b/', ' ', $text) ?? $text;

        $months = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';
        $text = preg_replace('/\b\d{1,2}\s+'.$months.'\s+\d{2,4}\b/i', ' ', $text) ?? $text;
        $text = preg_replace('/\b'.$months.'\s+\d{1,2},?\s+\d{2,4}\b/i', ' ', $text) ?? $text;
        $text = preg_replace('/\b(for|on|at)\s+\d{1,2}\b/i', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    protected function cleanNameCandidate(string $value): ?string
    {
        $value = $this->stripDatePhrases($value);
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

        if (! $this->isPlausiblePersonName($value)) {
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

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    protected function homeworkWeekReply(Student $student, ?array $snapshot = null): string
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return 'Homework is not enabled on this licence.';
        }

        $intro = $this->studentIntro($student);
        $homework = is_array($snapshot['homework'] ?? null)
            ? $snapshot['homework']
            : $this->studentData->homeworkSnapshot((int) $student->id);
        $today = $homework['today'] ?? [];
        $week = $homework['this_week'] ?? [];

        if (($today['unmarked_today'] ?? true) && ($today['marked_count'] ?? 0) === 0) {
            $weekNotDone = (int) ($week['not_done_count'] ?? 0);

            if ($weekNotDone > 0) {
                return $intro.' has **no homework marked for today yet**, but has **'.$weekNotDone.' Not Done** mark'.($weekNotDone === 1 ? '' : 's').' earlier this week.';
            }

            $recentNotDone = $this->latestNotDoneHomeworkCheck($homework);

            if ($recentNotDone !== null) {
                return $this->recentNotDoneHomeworkReply($intro, $recentNotDone);
            }

            return $intro.' has **no homework marked for today yet** and **no recent Not Done marks**. Homework may not have been checked yet.';
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

    /**
     * @param  array<string, mixed>  $homework
     * @return array<string, mixed>|null
     */
    protected function latestNotDoneHomeworkCheck(array $homework): ?array
    {
        foreach ($homework['recent_checks'] ?? [] as $check) {
            if (! is_array($check)) {
                continue;
            }

            if (($check['status'] ?? '') === HomeworkCheckStatus::NotDone->label()) {
                return $check;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $check
     */
    protected function recentNotDoneHomeworkReply(string $intro, array $check): string
    {
        $date = filled($check['date'] ?? null)
            ? Carbon::parse((string) $check['date'])->format('d M Y')
            : 'a recent date';
        $subject = trim((string) ($check['subject'] ?? 'homework'));
        $topic = trim((string) ($check['topic'] ?? ''));
        $detail = $topic !== '' ? $subject.' — '.$topic : $subject;

        return $intro.' has **no homework marked for today**. Latest check: **Not Done** on '.$date.' ('.$detail.').';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $checks
     */
    protected function homeworkCheckDetails(Collection $checks, string $statusLabel): string
    {
        return $checks
            ->where('status', $statusLabel)
            ->map(function (array $check): string {
                $subject = trim((string) ($check['subject'] ?? 'Subject'));
                $topic = trim((string) ($check['topic'] ?? ''));

                return $topic !== '' ? $subject.' — '.$topic : $subject;
            })
            ->implode('; ');
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
            ."• ABHINAV SINGH - homework for 9 Aug 2026\n"
            ."• ABHINAV SINGH homework status — whatsapp message for parent\n"
            ."• what about 9 Aug 2026? (follow-up after a student)\n"
            ."• how much fee pending for Ayyush\n"
            ."• and cases open for this student\n"
            .'• say “ask about someone else” to switch students';
    }

    /**
     * @return array{reply: string, intent: string, student_id: ?int, student_name: ?string, clear_context: bool}
     */
    protected function result(
        AskCrmIntent $intent,
        string $reply,
        ?int $studentId = null,
        ?string $studentName = null,
        bool $clearContext = false,
    ): array {
        return [
            'reply' => $reply,
            'intent' => $intent->value,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'clear_context' => $clearContext,
        ];
    }
}
