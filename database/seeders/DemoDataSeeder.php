<?php

namespace Database\Seeders;

use App\Enums\BatchShift;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\InstituteType;
use App\Enums\LeadSource;
use App\Enums\PaymentShortfallAction;
use App\Enums\RoleName;
use App\Enums\StudentCategory;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\Course;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityAttendanceService;
use App\Services\AdmissionService;
use App\Services\BatchService;
use App\Services\EnquiryService;
use App\Services\EnrollmentRollNumberService;
use App\Services\PaymentService;
use App\Services\StudentAuthService;
use App\Support\DefaultCourse;
use App\Support\DefaultProgrammes;
use App\Support\FeePaymentPolicy;
use App\Support\FeePlanCalculator;
use App\Support\InstituteProfile;
use App\Support\PaymentShortfallHelper;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $staff = $this->demoStaff();
        $superAdmin = $this->superAdmin();
        $session = AcademicSession::current();

        if (! $session) {
            $this->command?->error('Run AcademicSessionSeeder first.');

            return;
        }

        $instituteType = InstituteProfile::type();
        $this->seedDemoCourses($instituteType);
        $primaryCourse = $this->primaryCourseForType($instituteType);

        if (! $primaryCourse) {
            $this->command?->error('Run CourseSeeder first.');

            return;
        }

        $enquiries = app(EnquiryService::class);

        // --- Leads only (no student record yet) ---
        $enquiries->create([
            'name' => 'Aarav Sharma',
            'father_name' => 'Mr Sharma',
            'mobile' => '9811000001',
            'gender' => Gender::Male->value,
            'course_id' => $primaryCourse->id,
            'discussion_summary' => 'Walk-in — interested in '.$primaryCourse->name.'.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $enquiries->create([
            'name' => 'Priya Verma',
            'father_name' => 'Mrs Verma',
            'mobile' => '9811000002',
            'gender' => Gender::Female->value,
            'course_id' => $primaryCourse->id,
            'discussion_summary' => 'Website enquiry — follow up next week.',
            'visit_status' => 'follow_up_required',
        ], $staff, LeadSource::Website);

        $enquiries->create([
            'name' => 'Karan Mehta',
            'mobile' => '9811000003',
            'gender' => Gender::Male->value,
            'course_id' => DefaultCourse::undecided()->id,
            'discussion_summary' => 'Walk-in — course not decided yet.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        // --- Admission in progress (fee plan with installments, not yet enrolled) ---
        $neha = Student::query()->create([
            'name' => 'Neha Singh',
            'father_name' => 'Mr Singh',
            'date_of_birth' => '2002-04-18',
            'gender' => Gender::Female,
            'mobile' => '9811000004',
            'email' => 'neha.demo@example.com',
            'category' => StudentCategory::General,
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(StudentAuthService::class)->hashPortalPassword('18042002'),
        ]);

        $nehaEnquiry = $enquiries->createForExistingStudent($neha, [
            'course_id' => $primaryCourse->id,
            'discussion_summary' => 'Ready for admission — fee plan agreed.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $nehaNet = round((float) $primaryCourse->fee - 10000 + 5000, 2);
        $nehaHalf = round($nehaNet / 2, 2);
        $nehaBalance = round($nehaNet - $nehaHalf, 2);

        app(AdmissionService::class)->convert($neha, $nehaEnquiry, $staff, [
            'course_id' => $primaryCourse->id,
            'discount_amount' => 10000,
            'use_installment_plan' => true,
            'misc_fees' => [
                ['label' => 'Transport', 'amount' => 5000],
            ],
            'installment_plan' => [
                ['label' => 'Installment 1', 'amount' => $nehaHalf, 'due_date' => now()->toDateString()],
                ['label' => 'Installment 2', 'amount' => $nehaBalance, 'due_date' => now()->addMonth()->toDateString()],
            ],
        ]);

        // --- Fully enrolled student with installments + partial payment ---
        $demo = $this->demoProfileForType($instituteType);

        $batch = $this->createBatch(
            session: $session,
            course: $primaryCourse,
            staff: $staff,
            name: $demo['batch_name'],
            section: $demo['section'],
            shift: $demo['shift'],
        );

        $enrolledStudent = $this->seedEnrolledStudent(
            staff: $staff,
            approver: $superAdmin,
            name: $demo['name'],
            mobile: $demo['mobile'],
            dob: $demo['dob'],
            course: $primaryCourse,
            batch: $batch,
            discount: $demo['discount'],
            payment: $demo['payment'],
            gender: $demo['gender'],
        );

        $studentTwo = $this->seedEnrolledStudent(
            staff: $staff,
            approver: $superAdmin,
            name: 'Sneha Gupta',
            mobile: '9811000008',
            dob: '2001-03-12',
            course: $primaryCourse,
            batch: $batch,
            discount: 8000,
            payment: 12000,
            gender: Gender::Female,
        );

        $studentThree = $this->seedEnrolledStudent(
            staff: $staff,
            approver: $superAdmin,
            name: 'Amit Verma',
            mobile: '9811000009',
            dob: '2001-11-05',
            course: $primaryCourse,
            batch: $batch,
            discount: 5000,
            payment: 10000,
            gender: Gender::Male,
        );

        $rolls = app(EnrollmentRollNumberService::class);
        $enrollmentOne = $enrolledStudent->activeEnrollment;
        $enrollmentTwo = $studentTwo->activeEnrollment;
        $enrollmentThree = $studentThree->activeEnrollment;

        if ($enrollmentOne) {
            $rolls->update($enrollmentOne, '101', $staff);
        }

        if ($enrollmentTwo) {
            $rolls->update($enrollmentTwo, '102', $staff);
        }

        if ($enrollmentThree) {
            $rolls->update($enrollmentThree, '103', $staff);
        }

        $classStudents = [
            $enrolledStudent->fresh(),
            $studentTwo->fresh(),
            $studentThree->fresh(),
        ];

        $this->seedDemoActivities($staff, $batch, $classStudents, $instituteType);

        $this->printSummary($instituteType, $primaryCourse, $demo, $nehaNet);
    }

    protected function seedDemoCourses(InstituteType $type): void
    {
        foreach (DefaultProgrammes::forType($type) as $course) {
            Course::query()->updateOrCreate(
                ['code' => $course['code']],
                [
                    ...$course,
                    'status' => CourseStatus::Active,
                    'show_on_website' => true,
                ],
            );
        }
    }

    protected function primaryCourseForType(InstituteType $type): ?Course
    {
        $code = match ($type) {
            InstituteType::School => 'SCH-12-COM',
            InstituteType::Coaching => 'COACH-JEE-1Y',
            InstituteType::College => 'COL-BCOM-2Y',
        };

        return Course::query()->where('code', $code)->first();
    }

    /**
     * @return array{name: string, mobile: string, dob: string, dob_password: string, gender: Gender, batch_name: string, section: ?string, shift: BatchShift, discount: int, payment: int}
     */
    protected function demoProfileForType(InstituteType $type): array
    {
        return match ($type) {
            InstituteType::School => [
                'name' => 'Rohit Kumar',
                'mobile' => '9811000005',
                'dob' => '2001-08-09',
                'dob_password' => '09082001',
                'gender' => Gender::Male,
                'batch_name' => 'Class 12-A',
                'section' => 'A',
                'shift' => BatchShift::Morning,
                'discount' => 10000,
                'payment' => 15000,
            ],
            InstituteType::Coaching => [
                'name' => 'Arjun Patel',
                'mobile' => '9811000006',
                'dob' => '2003-01-15',
                'dob_password' => '15012003',
                'gender' => Gender::Male,
                'batch_name' => 'JEE Batch 2026',
                'section' => null,
                'shift' => BatchShift::Evening,
                'discount' => 5000,
                'payment' => 20000,
            ],
            InstituteType::College => [
                'name' => 'Ananya Reddy',
                'mobile' => '9811000007',
                'dob' => '2002-11-22',
                'dob_password' => '22112002',
                'gender' => Gender::Female,
                'batch_name' => 'B.Com Sem 2',
                'section' => 'B',
                'shift' => BatchShift::Morning,
                'discount' => 8000,
                'payment' => 18000,
            ],
        };
    }

    protected function createBatch(
        AcademicSession $session,
        Course $course,
        User $staff,
        string $name,
        ?string $section,
        BatchShift $shift,
    ): Batch {
        return Batch::query()->create([
            'name' => $name,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'section' => $section,
            'shift' => $shift,
            'trainer_user_id' => $staff->id,
            'start_date' => $session->starts_on->toDateString(),
            'end_date' => $session->ends_on->toDateString(),
            'status' => BatchStatus::Active,
        ]);
    }

    protected function seedEnrolledStudent(
        User $staff,
        User $approver,
        string $name,
        string $mobile,
        string $dob,
        Course $course,
        Batch $batch,
        int $discount,
        int $payment,
        Gender $gender = Gender::Male,
    ): Student {
        $dobPassword = app(StudentAuthService::class)->hashPortalPassword(
            str_replace('-', '', date('dmY', strtotime($dob))),
        );

        $student = Student::query()->create([
            'name' => $name,
            'father_name' => 'Mr '.explode(' ', $name)[0],
            'date_of_birth' => $dob,
            'gender' => $gender,
            'mobile' => $mobile,
            'email' => strtolower(str_replace(' ', '.', $name)).'.demo@example.com',
            'category' => StudentCategory::General,
            'status' => StudentStatus::Enquiry,
            'portal_password' => $dobPassword,
        ]);

        $netFee = round((float) $course->fee - $discount, 2);
        $installmentPlan = FeePlanCalculator::defaultTwoPartPlan($netFee);

        $enquiries = app(EnquiryService::class);
        $admissions = app(AdmissionService::class);

        $enquiry = $enquiries->createForExistingStudent($student, [
            'course_id' => $course->id,
            'discussion_summary' => "Enrolled demo — {$course->name}.",
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $admission = $admissions->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => $discount,
            'use_installment_plan' => true,
            'installment_plan' => $installmentPlan,
        ]);

        $admission = $admissions->submitForm(
            $admission,
            [
                'tenth_board' => 'CBSE',
                'tenth_percentage' => 82,
                'twelfth_board' => 'CBSE',
                'twelfth_percentage' => 78,
            ],
            [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
                'aadhaar' => UploadedFile::fake()->create('aadhaar.pdf', 50, 'application/pdf'),
                'marksheet' => UploadedFile::fake()->create('marksheet.pdf', 50, 'application/pdf'),
                'signature' => UploadedFile::fake()->image('sign.jpg'),
            ],
            $staff,
        );

        $enrollment = $admissions->approve($admission, $approver);
        app(BatchService::class)->assign($student, $batch, $staff);

        $feeStructure = $enrollment->feeStructure;

        if ($feeStructure && $payment > 0) {
            app(PaymentService::class)->add(
                $feeStructure,
                $student,
                $this->demoPaymentPayload(
                    $feeStructure,
                    $payment,
                    'DEMO-'.strtoupper(substr($name, 0, 3)),
                ),
                UploadedFile::fake()->image('proof.jpg'),
                $staff,
            );
        }

        return $student->fresh(['activeEnrollment']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function demoPaymentPayload(FeeStructure $feeStructure, float $amount, string $voucherNumber): array
    {
        $feeStructure->loadMissing('installments');

        $payload = [
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'payment_mode' => 'cash',
            'voucher_number' => $voucherNumber,
        ];

        if (! FeePaymentPolicy::usesFlexibleAllocation() || $feeStructure->installments->isEmpty()) {
            return $payload;
        }

        $installment = $feeStructure->installments->sortBy('sort_order')->first();

        if (! $installment || PaymentShortfallHelper::shortfallAmount($amount, $installment) <= 0) {
            return $payload;
        }

        if (PaymentShortfallHelper::hasNextPayableInstallment($installment)) {
            $payload['shortfall_action'] = PaymentShortfallAction::CarryForward->value;

            return $payload;
        }

        $payload['shortfall_action'] = PaymentShortfallAction::NewInstallment->value;
        $payload['shortfall_due_date'] = now()->addMonth()->toDateString();
        $payload['shortfall_label'] = PaymentShortfallHelper::suggestNewInstallmentLabel($feeStructure->id);

        return $payload;
    }

    /**
     * @param  list<Student>  $students
     */
    protected function seedDemoActivities(
        User $staff,
        Batch $batch,
        array $students,
        InstituteType $instituteType,
    ): void {
        $examType = ActivityType::query()->where('slug', 'exam')->first();
        $mockType = ActivityType::query()->where('slug', 'mock_test')->first();

        if (! $examType || ! $mockType) {
            $this->command?->warn('Exam types missing — run ActivityTypeSeeder first.');

            return;
        }

        $type = $instituteType === InstituteType::Coaching ? $mockType : $examType;
        $tests = $this->demoExamTestsForType($instituteType);

        foreach ($tests as $test) {
            $this->seedTestMarks($staff, $batch, $students, $type, $test);
        }

        $this->command?->line('Demo marks: '.count($tests).' test(s) · rolls 101–103 · student profile shows subject columns');
    }

    /**
     * @param  list<Student>  $students
     * @param  array{
     *     name: string,
     *     date: string,
     *     max_marks: int|float,
     *     subjects: array<string, array<string, int|float>>
     * }  $test
     */
    protected function seedTestMarks(
        User $staff,
        Batch $batch,
        array $students,
        ActivityType $type,
        array $test,
    ): void {
        $attendance = app(ActivityAttendanceService::class);
        $testKey = Str::slug($test['name']).'-'.$test['date'];

        foreach ($test['subjects'] as $subject => $marksByRoll) {
            $session = ActivitySession::query()->create([
                'activity_type_id' => $type->id,
                'title' => "{$test['name']} — {$subject}",
                'session_date' => $test['date'],
                'batch_id' => $batch->id,
                'metadata' => [
                    'test_key' => $testKey,
                    'test_name' => $test['name'],
                    'subject' => $subject,
                    'max_marks' => $test['max_marks'],
                ],
                'created_by_user_id' => $staff->id,
            ]);

            $scores = [];

            foreach ($students as $student) {
                $roll = strtoupper((string) ($student->activeEnrollment?->enrollment_number ?? ''));

                if ($roll === '' || ! isset($marksByRoll[$roll])) {
                    continue;
                }

                $scores[$student->id] = $marksByRoll[$roll];
            }

            if ($scores !== []) {
                $attendance->importStudentScores($session, $scores, $staff);
            }
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     date: string,
     *     max_marks: int|float,
     *     subjects: array<string, array<string, int|float>>
     * }>
     */
    protected function demoExamTestsForType(InstituteType $instituteType): array
    {
        return match ($instituteType) {
            InstituteType::School => [
                [
                    'name' => 'Unit Test — June 2026',
                    'date' => now()->subDays(15)->toDateString(),
                    'max_marks' => 50,
                    'subjects' => [
                        'Mathematics' => ['101' => 42, '102' => 38, '103' => 45],
                        'Physics' => ['101' => 35, '102' => 40, '103' => 33],
                        'Chemistry' => ['101' => 44, '102' => 41, '103' => 39],
                    ],
                ],
                [
                    'name' => 'Unit Test — July 2026',
                    'date' => now()->subDays(3)->toDateString(),
                    'max_marks' => 50,
                    'subjects' => [
                        'Mathematics' => ['101' => 40, '102' => 43, '103' => 41],
                        'Physics' => ['101' => 36, '102' => 38, '103' => 35],
                        'Chemistry' => ['101' => 42, '102' => 40, '103' => 44],
                        'Biology' => ['101' => 38, '102' => 36, '103' => 40],
                    ],
                ],
            ],
            InstituteType::Coaching => [
                [
                    'name' => 'Mock Test — June 2026',
                    'date' => now()->subDays(15)->toDateString(),
                    'max_marks' => 100,
                    'subjects' => [
                        'Physics' => ['101' => 72, '102' => 68, '103' => 75],
                        'Chemistry' => ['101' => 65, '102' => 70, '103' => 62],
                    ],
                ],
                [
                    'name' => 'Mock Test — July 2026',
                    'date' => now()->subDays(3)->toDateString(),
                    'max_marks' => 100,
                    'subjects' => [
                        'Physics' => ['101' => 78, '102' => 74, '103' => 80],
                        'Chemistry' => ['101' => 70, '102' => 72, '103' => 68],
                        'Mathematics' => ['101' => 82, '102' => 79, '103' => 85],
                    ],
                ],
            ],
            InstituteType::College => [
                [
                    'name' => 'Internal — June 2026',
                    'date' => now()->subDays(15)->toDateString(),
                    'max_marks' => 40,
                    'subjects' => [
                        'Business Law' => ['101' => 31, '102' => 28, '103' => 34],
                        'Financial Accounting' => ['101' => 36, '102' => 33, '103' => 38],
                    ],
                ],
                [
                    'name' => 'Internal — July 2026',
                    'date' => now()->subDays(3)->toDateString(),
                    'max_marks' => 40,
                    'subjects' => [
                        'Business Law' => ['101' => 33, '102' => 30, '103' => 35],
                        'Financial Accounting' => ['101' => 34, '102' => 32, '103' => 36],
                        'Economics' => ['101' => 29, '102' => 31, '103' => 30],
                    ],
                ],
            ],
        };
    }

    protected function demoStaff(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::query()->updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Staff',
                'password' => Hash::make('password'),
                'mobile' => '9999900000',
                'is_active' => true,
            ],
        );

        if (! $user->hasRole(RoleName::Staff->value)) {
            $user->assignRole(RoleName::Staff->value);
        }

        return $user;
    }

    protected function superAdmin(): User
    {
        $mobile = (string) env('ADMIN_MOBILE', '9876543210');

        return User::query()
            ->where('mobile', $mobile)
            ->orWhereHas('roles', fn ($query) => $query->where('name', RoleName::SuperAdmin->value))
            ->firstOrFail();
    }

    protected function printSummary(InstituteType $instituteType, Course $course, array $demo, float $nehaNet): void
    {
        $this->command?->newLine();
        $this->command?->info('=== Fresh demo data ('.$instituteType->label().') ===');
        $this->command?->line('Primary course: '.$course->name.' · Fee ₹'.number_format((float) $course->fee, 2));
        $this->command?->newLine();
        $this->command?->line('Super Admin: '.env('ADMIN_EMAIL', 'rohit03993@gmail.com').' / '.env('ADMIN_PASSWORD', 'Admin@2026'));
        $this->command?->line('Staff: demo@example.com / password');
        $this->command?->newLine();
        $this->command?->line('Leads: Aarav 9811000001 · Priya 9811000002 · Karan 9811000003 (undecided course)');
        $this->command?->line("Admission pending: Neha Singh 9811000004 · net ₹".number_format($nehaNet, 2).' · 2 installments + transport');
        $this->command?->line("Enrolled: {$demo['name']} roll 101 · Sneha Gupta roll 102 · Amit Verma roll 103 · partial fees paid");
        $this->command?->line('Marks: Student profile → Exam tab — Unit Test June & July (subjects as columns)');
        $this->command?->line('Import Marks: use rolls 101–103 · template under Academics → Import Marks');
    }
}
