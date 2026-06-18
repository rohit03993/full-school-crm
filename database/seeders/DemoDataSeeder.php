<?php

namespace Database\Seeders;

use App\Enums\BatchShift;
use App\Enums\BatchStatus;
use App\Enums\Gender;
use App\Enums\InstituteType;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentCategory;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityAttendanceService;
use App\Services\AdmissionService;
use App\Services\BatchService;
use App\Services\EnquiryService;
use App\Services\PaymentService;
use App\Services\StudentAuthService;
use App\Support\DefaultCourse;
use App\Support\FeePlanCalculator;
use App\Support\InstituteProfile;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $staff = $this->demoStaff();
        $session = AcademicSession::current();

        if (! $session) {
            $this->command?->error('Run AcademicSessionSeeder first.');

            return;
        }

        $instituteType = InstituteProfile::type();
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
            name: $demo['name'],
            mobile: $demo['mobile'],
            dob: $demo['dob'],
            course: $primaryCourse,
            batch: $batch,
            discount: $demo['discount'],
            payment: $demo['payment'],
            gender: $demo['gender'],
        );

        $this->seedDemoActivities($staff, $batch, $enrolledStudent, $instituteType);

        $this->printSummary($instituteType, $primaryCourse, $demo, $nehaNet);
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

        $enrollment = $admissions->approve($admission, $staff);
        app(BatchService::class)->assign($student, $batch, $staff);

        $feeStructure = $enrollment->feeStructure;

        if ($feeStructure && $payment > 0) {
            app(PaymentService::class)->add(
                $feeStructure,
                $student,
                [
                    'payment_date' => now()->toDateString(),
                    'amount' => $payment,
                    'payment_mode' => 'cash',
                    'voucher_number' => 'DEMO-'.strtoupper(substr($name, 0, 3)),
                ],
                UploadedFile::fake()->image('proof.jpg'),
                $staff,
            );
        }

        return $student;
    }

    protected function seedDemoActivities(
        User $staff,
        Batch $batch,
        Student $student,
        InstituteType $instituteType,
    ): void {
        $examType = ActivityType::query()->where('slug', 'exam')->first();
        $mockType = ActivityType::query()->where('slug', 'mock_test')->first();

        if (! $examType || ! $mockType) {
            $this->command?->warn('Activity types missing — run ActivityTypeSeeder first.');

            return;
        }

        $attendance = app(ActivityAttendanceService::class);

        [$type, $title, $metadata, $marks] = match ($instituteType) {
            InstituteType::School => [
                $examType,
                'Unit Test — Accountancy',
                ['subject' => 'Accountancy', 'max_marks' => 50],
                ['marks_obtained' => 38],
            ],
            InstituteType::Coaching => [
                $mockType,
                'JEE Mock — Paper 1',
                ['paper' => 'Physics & Chemistry', 'max_marks' => 100],
                ['marks_obtained' => 72],
            ],
            InstituteType::College => [
                $examType,
                'Internal — Business Law',
                ['subject' => 'Business Law', 'max_marks' => 40],
                ['marks_obtained' => 31],
            ],
        };

        $session = ActivitySession::query()->create([
            'activity_type_id' => $type->id,
            'title' => $title,
            'session_date' => now()->subDays(2)->toDateString(),
            'batch_id' => $batch->id,
            'metadata' => $metadata,
            'created_by_user_id' => $staff->id,
        ]);

        $attendance->saveMarks(
            $session,
            [$student->id => true],
            $staff,
            [$student->id => $marks],
        );
    }

    protected function demoStaff(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::query()->create([
            'name' => 'Demo Staff',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
            'mobile' => '9999900000',
            'is_active' => true,
        ]);

        $user->assignRole(RoleName::Staff->value);

        return $user;
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
        $this->command?->line("Enrolled: {$demo['name']} {$demo['mobile']} · portal password DOB {$demo['dob_password']} · partial fee paid");
    }
}
