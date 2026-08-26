<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\EnrolledCallPurpose;
use App\Enums\Gender;
use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\InstituteType;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Enums\WhoAnswered;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Course;
use App\Models\ExamWindow;
use App\Models\FeeStructure;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\Payment;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Models\Visit;
use App\Services\ActivityAttendanceService;
use App\Services\AdmissionService;
use App\Services\CallLogService;
use App\Services\EnquiryService;
use App\Services\ExamWindowService;
use App\Services\FeeInstallmentService;
use App\Services\PaymentService;
use App\Services\PenaltyCalculationService;
use App\Services\LeadAssignmentService;
use App\Services\ResultDeclarationService;
use App\Models\Setting;
use App\Support\CrmCacheInvalidator;
use App\Support\FeePaymentPolicy;
use App\Support\InstituteProfile;
use App\Support\PaymentShortfallHelper;
use App\Services\CrmDashboardService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Additive operational history for school campus demo (attendance, fees, marks,
 * leads, calls, homework checks). Safe to re-run — each slice skips if already present.
 *
 * Does not change product behaviour for paid schools unless this seeder is run there.
 */
class DemoOperationalHistorySeeder extends Seeder
{
    public const MARKER_VOUCHER_PREFIX = 'DEMO-OPS-';

    public const LEAD_MOBILE_START = 9813000001;

    public const MIDTERM_TEST_NAME = 'Demo Mid-Term';

    /** Weekdays of history (~3 months). */
    public const HISTORY_WEEKDAYS = 65;

    public function run(): void
    {
        if (InstituteProfile::type() !== InstituteType::School) {
            $this->command?->warn('DemoOperationalHistorySeeder skipped — not a school institute.');

            return;
        }

        if (! Course::query()->where('code', 'SCH-05')->exists()) {
            $this->command?->warn('DemoOperationalHistorySeeder skipped — run DemoSchoolCampusSeeder first.');

            return;
        }

        $admin = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::SuperAdmin->value))
            ->first();
        $accountant = User::query()->where('email', 'accounts01@example.com')->first() ?? $admin;
        $counsellor = User::query()->where('email', 'counsellor01@example.com')->first() ?? $admin;
        $admissionOfficer = User::query()->where('email', 'admission01@example.com')->first() ?? $admin;

        if (! $admin || ! $accountant) {
            $this->command?->error('Need Super Admin (+ accounts staff) before operational history seed.');

            return;
        }

        $campusBatches = $this->campusBatches();
        if ($campusBatches->isEmpty()) {
            $this->command?->warn('No campus batches found.');

            return;
        }

        $this->command?->info('Seeding demo operational history…');

        Batch::query()
            ->where('name', 'Class 12-A')
            ->whereHas('course', fn ($q) => $q->where('code', 'SCH-12-COM'))
            ->update(['name' => 'Commerce Demo 12-A']);

        $this->cleanupSeedVisitNoise();
        $this->seedStudentAttendanceHistory($campusBatches, $admin);
        $this->seedStaffAttendanceHistory($admin);
        $this->seedCampusFees($admin);
        $this->seedExtraMarksAndPublish($campusBatches, $admin);
        $this->seedHomeworkChecks($campusBatches, $admin);
        $this->seedPipelineLeadsCallsAndAdmissions($counsellor, $admissionOfficer, $admin);

        CrmCacheInvalidator::afterEnquiryChange();
        CrmCacheInvalidator::afterAdmissionChange();
        CrmCacheInvalidator::afterPayment();
        CrmCacheInvalidator::afterAttendanceChange();
        CrmDashboardService::flushAllCaches();

        $this->command?->newLine();
        $this->command?->info('=== Operational history ready ===');
        $this->command?->line('Student + staff attendance: ~'.self::HISTORY_WEEKDAYS.' weekdays');
        $this->command?->line('Campus fees: installments + Cash/UPI/Online/Cheque mix over ~3 months');
        $this->command?->line('Marks: Demo Mid-Term (Class 8–12) with one published result set');
        $this->command?->line('Pipeline: ~25 leads, calls, 2 pending admissions, homework checks');
        $this->command?->line('Re-run safe — each slice skips when already present.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Batch>
     */
    protected function campusBatches()
    {
        $codes = collect(range(5, 12))->map(fn (int $n): string => sprintf('SCH-%02d', $n));
        $courseIds = Course::query()->whereIn('code', $codes)->pluck('id');

        return Batch::query()
            ->whereIn('course_id', $courseIds)
            ->where('name', 'like', 'Class %')
            ->with(['activeStudents', 'course', 'activeSubjects', 'trainer'])
            ->orderBy('name')
            ->get();
    }

    protected function cleanupSeedVisitNoise(): void
    {
        $deleted = Visit::query()
            ->where('remarks', 'Seeded for sales demo (not a live lead).')
            ->delete();

        if ($deleted > 0) {
            $this->command?->line("  Cleaned {$deleted} campus seed Joined visits (visit noise).");
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Batch>  $batches
     */
    protected function seedStudentAttendanceHistory($batches, User $marker): void
    {
        $sampleBatchId = $batches->first()?->id;
        if (! $sampleBatchId) {
            return;
        }

        $oldExists = Attendance::query()
            ->where('batch_id', $sampleBatchId)
            ->whereDate('attendance_date', '<=', now()->subDays(20)->toDateString())
            ->exists();

        if ($oldExists) {
            $this->command?->line('  Student attendance history: already present — skip.');

            return;
        }

        $this->command?->line('  Student attendance history…');
        $weekdays = $this->weekdayDates(self::HISTORY_WEEKDAYS);
        $now = now();
        $rows = [];

        foreach ($batches as $batch) {
            $studentIds = $batch->activeStudents->pluck('student_id')->all();
            if ($studentIds === []) {
                continue;
            }

            foreach ($weekdays as $date) {
                foreach ($studentIds as $i => $studentId) {
                    $roll = ($studentId + $date->dayOfYear) % 20;
                    $status = match (true) {
                        $roll === 0 => AttendanceStatus::Leave->value,
                        $roll <= 2 => AttendanceStatus::Absent->value,
                        default => AttendanceStatus::Present->value,
                    };

                    $rows[] = [
                        'batch_id' => $batch->id,
                        'student_id' => $studentId,
                        'attendance_date' => $date->toDateString(),
                        'status' => $status,
                        'punch_source' => 'roll_call',
                        'marked_by_user_id' => $marker->id,
                        'checked_in_at' => $status === AttendanceStatus::Present->value
                            ? $date->copy()->setTime(8, 15 + ($i % 20))->toDateTimeString()
                            : null,
                        'checked_out_at' => $status === AttendanceStatus::Present->value
                            ? $date->copy()->setTime(14, 0)->toDateTimeString()
                            : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($rows) >= 500) {
                        DB::table('attendances')->insertOrIgnore($rows);
                        $rows = [];
                    }
                }
            }
        }

        if ($rows !== []) {
            DB::table('attendances')->insertOrIgnore($rows);
        }

        $this->command?->line('  Student attendance history: done ('.count($weekdays).' weekdays × sections).');
    }

    protected function seedStaffAttendanceHistory(User $actor): void
    {
        $teachers = User::query()
            ->where('email', 'like', 'teacher%@example.com')
            ->orderBy('email')
            ->get();

        $office = User::query()
            ->whereIn('email', [
                'academic.coord@example.com',
                'counsellor01@example.com',
                'admission01@example.com',
                'accounts01@example.com',
            ])
            ->get();

        $staff = $teachers->concat($office)->unique('id')->values();
        if ($staff->isEmpty()) {
            return;
        }

        $sampleId = $staff->first()->id;
        $oldExists = StaffAttendance::query()
            ->where('user_id', $sampleId)
            ->whereDate('attendance_date', '<=', now()->subDays(20)->toDateString())
            ->exists();

        if ($oldExists) {
            $this->command?->line('  Staff attendance history: already present — skip.');

            return;
        }

        $this->command?->line('  Staff attendance history…');
        $weekdays = $this->weekdayDates(self::HISTORY_WEEKDAYS);
        $now = now();
        $rows = [];

        foreach ($staff as $member) {
            foreach ($weekdays as $di => $date) {
                $roll = ($member->id + $di) % 25;
                $status = match (true) {
                    $roll === 0 => AttendanceStatus::Leave->value,
                    $roll === 1 => AttendanceStatus::Absent->value,
                    default => AttendanceStatus::Present->value,
                };

                $rows[] = [
                    'user_id' => $member->id,
                    'attendance_date' => $date->toDateString(),
                    'status' => $status,
                    'punch_source' => 'manual',
                    'marked_by_user_id' => $actor->id,
                    'checked_in_at' => $status === AttendanceStatus::Present->value
                        ? $date->copy()->setTime(7, 50)->toDateTimeString()
                        : null,
                    'checked_out_at' => $status === AttendanceStatus::Present->value
                        ? $date->copy()->setTime(15, 10)->toDateTimeString()
                        : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= 500) {
                    DB::table('staff_attendances')->insertOrIgnore($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('staff_attendances')->insertOrIgnore($rows);
        }

        $this->command?->line('  Staff attendance history: done ('.$staff->count().' staff × '.count($weekdays).' days).');
    }

    protected function seedCampusFees(User $staff): void
    {
        if (Setting::getValue('demo_ops_fees_v1', null) === '1'
            || Payment::query()->where('voucher_number', 'like', self::MARKER_VOUCHER_PREFIX.'%')->exists()
            || Payment::query()->where('transaction_id', 'like', self::MARKER_VOUCHER_PREFIX.'%')->exists()
            || Payment::query()->where('utr_number', 'like', self::MARKER_VOUCHER_PREFIX.'%')->exists()) {
            $this->command?->line('  Campus fees: already present — skip.');

            return;
        }

        $this->command?->line('  Campus fees (installments + payments)…');

        $codes = collect(range(5, 12))->map(fn (int $n): string => sprintf('SCH-%02d', $n));
        $courseIds = Course::query()->whereIn('code', $codes)->pluck('id');

        $structures = FeeStructure::query()
            ->whereHas('enrollment', fn ($q) => $q->whereIn('course_id', $courseIds)->where('is_active', true))
            ->with(['enrollment.student', 'installments'])
            ->where('pending_amount', '>', 0)
            ->where('paid_amount', '<=', 0)
            ->get();

        $payments = app(PaymentService::class);
        $installments = app(FeeInstallmentService::class);
        $penalties = app(PenaltyCalculationService::class);
        $proof = UploadedFile::fake()->image('demo-ops-proof.jpg');

        $paid = 0;
        $skipped = 0;

        foreach ($structures as $index => $feeStructure) {
            $student = $feeStructure->enrollment?->student;
            if (! $student) {
                $skipped++;

                continue;
            }

            $bucket = $index % 10;
            $net = round((float) $feeStructure->pending_amount, 2);
            if ($net <= 0) {
                continue;
            }

            $term1 = round($net * 0.4, 2);
            $term2 = round($net - $term1, 2);

            try {
                if ($bucket <= 3) {
                    // 40% — mostly paid over months
                    $installments->reschedulePendingInstallments($feeStructure, [
                        ['label' => 'Term 1', 'amount' => $term1, 'due_date' => now()->subMonths(2)->toDateString(), 'sort_order' => 1],
                        ['label' => 'Term 2', 'amount' => $term2, 'due_date' => now()->subDays(20)->toDateString(), 'sort_order' => 2],
                    ]);
                    $fs = $feeStructure->fresh(['installments']);
                    $this->payInstallment($payments, $fs, $student, $staff, $proof, $term1, now()->subMonths(2)->addDays(3), $index, 1);
                    $this->payInstallment($payments, $fs->fresh(['installments']), $student, $staff, $proof, $term2, now()->subDays(15), $index, 2);
                    $paid++;
                } elseif ($bucket <= 6) {
                    // 30% — partial (first term only)
                    $installments->reschedulePendingInstallments($feeStructure, [
                        ['label' => 'Term 1', 'amount' => $term1, 'due_date' => now()->subMonth()->toDateString(), 'sort_order' => 1],
                        ['label' => 'Term 2', 'amount' => $term2, 'due_date' => now()->addMonth()->toDateString(), 'sort_order' => 2],
                    ]);
                    $fs = $feeStructure->fresh(['installments']);
                    $this->payInstallment($payments, $fs, $student, $staff, $proof, $term1, now()->subDays(25), $index, 1);
                    $paid++;
                } elseif ($bucket <= 8) {
                    // 20% — unpaid, future due
                    $installments->reschedulePendingInstallments($feeStructure, [
                        ['label' => 'Term 1', 'amount' => $term1, 'due_date' => now()->addDays(15)->toDateString(), 'sort_order' => 1],
                        ['label' => 'Term 2', 'amount' => $term2, 'due_date' => now()->addMonths(2)->toDateString(), 'sort_order' => 2],
                    ]);
                } else {
                    // 10% — overdue + late fee
                    $installments->reschedulePendingInstallments($feeStructure, [
                        ['label' => 'Term 1', 'amount' => $term1, 'due_date' => now()->subDays(45)->toDateString(), 'sort_order' => 1],
                        ['label' => 'Term 2', 'amount' => $term2, 'due_date' => now()->addMonth()->toDateString(), 'sort_order' => 2],
                    ]);
                    $first = $feeStructure->fresh(['installments'])->installments->sortBy('sort_order')->first();
                    if ($first) {
                        $penalties->processInstallmentPenalty($first, now());
                    }
                }
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning('Demo ops fee seed skipped for fee_structure '.$feeStructure->id.': '.$e->getMessage());
            }

            if (($index + 1) % 50 === 0) {
                $this->command?->line('    … fees progress '.($index + 1).'/'.$structures->count());
            }
        }

        Setting::setValue('demo_ops_fees_v1', '1', 'demo');
        $this->command?->line("  Campus fees: payment groups applied (~{$paid} with collections; {$skipped} skipped).");
    }

    protected function payInstallment(
        PaymentService $payments,
        FeeStructure $feeStructure,
        Student $student,
        User $staff,
        UploadedFile $proof,
        float $amount,
        Carbon $paymentDate,
        int $index,
        int $part,
    ): void {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }

        $mode = match ($index % 4) {
            0 => PaymentMode::Cash,
            1 => PaymentMode::Upi,
            2 => PaymentMode::Online,
            default => PaymentMode::Cheque,
        };

        $marker = self::MARKER_VOUCHER_PREFIX.$feeStructure->id.'-'.$part;
        $payload = [
            'payment_date' => $paymentDate->toDateString(),
            'amount' => $amount,
            'payment_mode' => $mode->value,
        ];

        $payload = array_merge($payload, match ($mode) {
            PaymentMode::Cash => ['voucher_number' => $marker],
            PaymentMode::Upi => ['utr_number' => $marker],
            PaymentMode::Online => ['transaction_id' => $marker],
            PaymentMode::Cheque => [
                'cheque_number' => str_pad((string) (($index * 10 + $part) % 1000000), 6, '0', STR_PAD_LEFT),
                'cheque_date' => $paymentDate->toDateString(),
                'cheque_bank_name' => 'Demo Bank',
                'voucher_number' => $marker,
            ],
        });

        $feeStructure->loadMissing('installments');
        if (FeePaymentPolicy::usesFlexibleAllocation() && $feeStructure->installments->isNotEmpty()) {
            $installment = $feeStructure->installments->sortBy('sort_order')->first(
                fn ($row) => (float) $row->pending_amount > 0.01
            );
            if ($installment && PaymentShortfallHelper::shortfallAmount($amount, $installment) > 0) {
                if (PaymentShortfallHelper::hasNextPayableInstallment($installment)) {
                    $payload['shortfall_action'] = PaymentShortfallAction::CarryForward->value;
                } else {
                    $payload['shortfall_action'] = PaymentShortfallAction::NewInstallment->value;
                    $payload['shortfall_due_date'] = now()->addMonth()->toDateString();
                    $payload['shortfall_label'] = PaymentShortfallHelper::suggestNewInstallmentLabel($feeStructure->id);
                }
            }
            if ($installment) {
                $payload['fee_installment_id'] = $installment->id;
            }
        }

        $payments->add($feeStructure->fresh(['installments', 'enrollment']), $student, $payload, $proof, $staff);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Batch>  $batches
     */
    protected function seedExtraMarksAndPublish($batches, User $admin): void
    {
        if (ExamWindow::query()->where('test_name', self::MIDTERM_TEST_NAME)->exists()) {
            $this->command?->line('  Demo Mid-Term exam windows: already present — skip.');

            return;
        }

        $examType = ActivityType::query()->where('slug', 'exam')->first();
        if (! $examType) {
            $this->command?->warn('  No exam activity type — skip marks publish.');

            return;
        }

        $this->command?->line('  Extra marks + publish Demo Mid-Term…');
        $windows = app(ExamWindowService::class);
        $attendance = app(ActivityAttendanceService::class);
        $results = app(ResultDeclarationService::class);

        $sessionDate = now()->subDays(40)->toDateString();
        $publishedOne = false;

        foreach ($batches as $batch) {
            $classNo = $this->classNumberFromBatch($batch);
            if ($classNo === null || $classNo < 8) {
                continue;
            }

            $students = Student::query()
                ->whereIn('id', $batch->activeStudents->pluck('student_id'))
                ->get();

            if ($students->isEmpty() || $batch->activeSubjects->isEmpty()) {
                continue;
            }

            try {
                $window = $windows->create([
                    'batch_id' => $batch->id,
                    'activity_type_id' => $examType->id,
                    'test_name' => self::MIDTERM_TEST_NAME,
                    'session_date' => $sessionDate,
                    'open_immediately' => true,
                    'remarks' => 'Demo operational history mid-term.',
                ], $admin);

                $sessions = ActivitySession::query()
                    ->where('batch_id', $batch->id)
                    ->where('metadata->test_key', $window->test_key)
                    ->get();

                $teacher = $batch->trainer ?? $admin;

                foreach ($sessions as $session) {
                    $max = (float) ($session->metadataValue('max_marks') ?? 100);
                    $scores = [];
                    foreach ($students as $i => $student) {
                        $scores[$student->id] = max(0, min($max, round($max * (0.55 + (($i % 10) * 0.04)), 1)));
                    }
                    $attendance->importStudentScores($session, $scores, $teacher);
                }

                $windows->submit($window->fresh(), $admin);
                $windows->approve($window->fresh(), $admin);

                if (! $publishedOne) {
                    $results->publish($window->test_key, $admin, now()->subDays(35)->toDateString());
                    $publishedOne = true;
                    $this->command?->line("    Published results for {$batch->name}.");
                }
            } catch (ValidationException $e) {
                Log::warning('Demo mid-term seed failed for batch '.$batch->id.': '.json_encode($e->errors()));
                $this->command?->warn('    Mid-term skipped for '.$batch->name.': '.collect($e->errors())->flatten()->first());
            } catch (\Throwable $e) {
                Log::warning('Demo mid-term seed failed for batch '.$batch->id.': '.$e->getMessage());
                $this->command?->warn('    Mid-term skipped for '.$batch->name.': '.$e->getMessage());
            }
        }

        // Extra light unit tests for Class 8–9 (no exam window) if none exist.
        foreach ($batches as $batch) {
            $classNo = $this->classNumberFromBatch($batch);
            if ($classNo === null || $classNo < 8 || $classNo > 9) {
                continue;
            }

            $existing = ActivitySession::query()
                ->where('batch_id', $batch->id)
                ->where('title', 'like', 'Unit Test — %')
                ->exists();

            if ($existing) {
                continue;
            }

            $students = Student::query()
                ->whereIn('id', $batch->activeStudents->pluck('student_id'))
                ->get()
                ->all();
            $teacher = $batch->trainer ?? $admin;

            foreach ($batch->activeSubjects->take(2) as $subject) {
                $session = ActivitySession::query()->create([
                    'activity_type_id' => $examType->id,
                    'title' => "Unit Test — {$subject->name}",
                    'session_date' => now()->subDays(18)->toDateString(),
                    'batch_id' => $batch->id,
                    'created_by_user_id' => $teacher->id,
                    'metadata' => [
                        'test_key' => 'ops-unit-'.$batch->id.'-'.$subject->id,
                        'test_name' => 'Unit Test Ops',
                        'subject' => $subject->name,
                        'max_marks' => 40,
                    ],
                ]);
                $scores = [];
                foreach ($students as $i => $student) {
                    $scores[$student->id] = 24 + ($i % 15);
                }
                try {
                    $attendance->importStudentScores($session, $scores, $teacher);
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        $this->command?->line('  Marks / publish: done.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Batch>  $batches
     */
    protected function seedHomeworkChecks($batches, User $teacherFallback): void
    {
        if (HomeworkCheck::query()->where('topic', 'like', 'Demo HW%')->exists()) {
            $this->command?->line('  Homework checks: already present — skip.');

            return;
        }

        $this->command?->line('  Homework checks…');
        $created = 0;

        foreach ($batches->take(8) as $batch) {
            $assignment = HomeworkAssignment::query()
                ->where('batch_id', $batch->id)
                ->orderByDesc('id')
                ->first();
            $subject = $batch->activeSubjects->first();
            if (! $subject) {
                continue;
            }

            $teacher = $batch->trainer ?? $teacherFallback;
            $studentIds = $batch->activeStudents->pluck('student_id')->take(12);

            foreach ($studentIds as $i => $studentId) {
                HomeworkCheck::query()->create([
                    'student_id' => $studentId,
                    'batch_id' => $batch->id,
                    'course_subject_id' => $subject->id,
                    'homework_assignment_id' => $assignment?->id,
                    'subject_name' => $subject->name,
                    'topic' => 'Demo HW — '.$batch->name.' worksheet',
                    'checked_on' => now()->subDays(5 + ($i % 7))->toDateString(),
                    'status' => $i % 4 === 0 ? HomeworkCheckStatus::NotDone : HomeworkCheckStatus::Done,
                    'notify_status' => HomeworkCheckNotifyStatus::NotRequired,
                    'created_by_user_id' => $teacher->id,
                ]);
                $created++;
            }
        }

        $this->command?->line("  Homework checks: {$created} rows.");
    }

    protected function seedPipelineLeadsCallsAndAdmissions(
        User $counsellor,
        User $admissionOfficer,
        User $admin,
    ): void {
        $mobile = (string) self::LEAD_MOBILE_START;
        if (Student::query()->where('mobile', $mobile)->exists()) {
            $this->command?->line('  Pipeline leads: already present — skip leads/admissions block.');
            $this->seedCallsOnly($counsellor);

            return;
        }

        $this->command?->line('  Pipeline leads, calls, pending admissions…');
        $enquiries = app(EnquiryService::class);
        $admissions = app(AdmissionService::class);
        $calls = app(CallLogService::class);

        $course = Course::query()->where('code', 'SCH-10')->first()
            ?? Course::query()->where('code', 'like', 'SCH-%')->first();

        $statuses = [
            VisitStatus::Interested,
            VisitStatus::FollowUpRequired,
            VisitStatus::AdmissionReady,
            VisitStatus::NotInterested,
            VisitStatus::Interested,
        ];
        $sources = [LeadSource::WalkIn, LeadSource::Website, LeadSource::StudentReference, LeadSource::Google];

        $leadStudents = [];
        for ($i = 0; $i < 25; $i++) {
            $leadMobile = (string) (self::LEAD_MOBILE_START + $i);
            $status = $statuses[$i % count($statuses)];
            $source = $sources[$i % count($sources)];

            try {
                $enquiry = $enquiries->create([
                    'name' => 'Demo Lead '.($i + 1),
                    'father_name' => 'Parent '.($i + 1),
                    'mobile' => $leadMobile,
                    'gender' => $i % 2 === 0 ? Gender::Male->value : Gender::Female->value,
                    'course_id' => $course?->id,
                    'meeting_with_user_id' => $counsellor->id,
                    'visit_type' => 'first_visit',
                    'visit_status' => $status->value,
                    'discussion_summary' => 'Demo pipeline lead for QA — interested in Class 10/11.',
                    'next_followup_at' => $status === VisitStatus::FollowUpRequired
                        ? now()->addDays($i % 5)->format('Y-m-d H:i:s')
                        : null,
                ], $counsellor, $source);

                $student = $enquiry->student;
                if ($student) {
                    try {
                        app(LeadAssignmentService::class)->assignForCalling(
                            $enquiry->fresh(),
                            $counsellor,
                            $admin,
                            'Demo ops calling assignment.',
                            false,
                        );
                    } catch (\Throwable) {
                        $enquiry->update([
                            'meeting_with_user_id' => $counsellor->id,
                            'calling_assigned_at' => now()->subDays(($i % 10) + 1),
                            'calling_assigned_by_user_id' => $admin->id,
                        ]);
                    }
                    $leadStudents[] = $student->fresh();
                }
            } catch (\Throwable $e) {
                Log::warning('Demo lead seed failed: '.$e->getMessage());
            }
        }

        // Calls on leads (prefer not-connected / service path to limit WhatsApp side effects)
        foreach (array_slice($leadStudents, 0, 15) as $i => $student) {
            try {
                if ($i % 3 === 0) {
                    $calls->log($student, $counsellor, [
                        'call_connected' => true,
                        'call_direction' => CallDirection::Outgoing->value,
                        'who_answered' => WhoAnswered::Father->value,
                        'visit_status' => VisitStatus::Interested->value,
                        'call_notes' => '[demo-ops] Parent asked about fees and transport options for next session.',
                        'next_followup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                    ]);
                } else {
                    $calls->log($student, $counsellor, [
                        'call_connected' => false,
                        'call_direction' => CallDirection::Outgoing->value,
                        'call_status' => CallStatus::NoAnswer->value,
                        'call_notes' => '[demo-ops] No answer — will retry tomorrow.',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Demo lead call failed: '.$e->getMessage());
            }
        }

        // Enrolled student service calls
        $enrolled = Student::query()
            ->whereHas('activeEnrollment.course', fn ($q) => $q->where('code', 'like', 'SCH-%'))
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($enrolled as $i => $student) {
            try {
                $calls->logForEnrolledStudent($student, $counsellor, [
                    'call_connected' => true,
                    'call_direction' => CallDirection::Outgoing->value,
                    'who_answered' => WhoAnswered::Mother->value,
                    'call_purpose' => ($i % 2 === 0)
                        ? EnrolledCallPurpose::FeeQuery->value
                        : EnrolledCallPurpose::Attendance->value,
                    'call_notes' => '[demo-ops] Discussed pending fees / attendance with parent for this week.',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Demo enrolled call failed: '.$e->getMessage());
            }
        }

        // 2 pending admissions (Submitted)
        foreach (array_slice($leadStudents, 0, 2) as $i => $student) {
            $enquiry = $student->enquiries()->latest('id')->first();
            if (! $enquiry || ! $course) {
                continue;
            }

            try {
                $admissions->convert($student, $enquiry, $admissionOfficer, [
                    'course_id' => $course->id,
                    'discount_amount' => 0,
                    'use_installment_plan' => false,
                ]);
                $this->command?->line('    Pending admission created for '.$student->name);
            } catch (\Throwable $e) {
                Log::warning('Demo pending admission failed: '.$e->getMessage());
                $this->command?->warn('    Pending admission skipped: '.$e->getMessage());
            }
        }

        $this->command?->line('  Pipeline: done.');
    }

    protected function seedCallsOnly(User $counsellor): void
    {
        if (StudentCall::query()->where('call_notes', 'like', '[demo-ops]%')->exists()) {
            $this->command?->line('  Calls: already present — skip.');

            return;
        }

        $calls = app(CallLogService::class);
        $enrolled = Student::query()
            ->whereHas('activeEnrollment.course', fn ($q) => $q->where('code', 'like', 'SCH-%'))
            ->orderBy('id')
            ->limit(15)
            ->get();

        foreach ($enrolled as $student) {
            try {
                $calls->logForEnrolledStudent($student, $counsellor, [
                    'call_connected' => true,
                    'who_answered' => WhoAnswered::Father->value,
                    'call_purpose' => EnrolledCallPurpose::General->value,
                    'call_notes' => '[demo-ops] Follow-up call logged for demo operational history.',
                ]);
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    /**
     * @return list<Carbon>
     */
    protected function weekdayDates(int $count): array
    {
        $dates = [];
        $cursor = now()->startOfDay();

        while (count($dates) < $count) {
            if ($cursor->isWeekday()) {
                $dates[] = $cursor->copy();
            }
            $cursor->subDay();
        }

        return array_reverse($dates);
    }

    protected function classNumberFromBatch(Batch $batch): ?int
    {
        if (preg_match('/Class\s+(\d+)/', (string) $batch->name, $m)) {
            return (int) $m[1];
        }

        $code = $batch->course?->code;
        if ($code && preg_match('/SCH-(\d+)/', $code, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
