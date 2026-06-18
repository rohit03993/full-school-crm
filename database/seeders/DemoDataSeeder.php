<?php

namespace Database\Seeders;

use App\Enums\BatchStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentCategory;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\BatchService;
use App\Services\EnquiryService;
use App\Services\PaymentService;
use App\Services\StudentAuthService;
use App\Support\DefaultCourse;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Student::query()->exists() && ! filter_var(env('DEMO_SEED_FORCE', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Demo data skipped — students already exist. Set DEMO_SEED_FORCE=true in .env to seed anyway.');

            return;
        }

        $staff = $this->demoStaff();
        $diploma = Course::query()->where('code', 'DIP-DCA-6M')->first()
            ?? Course::query()->where('course_type', 'diploma')->first();
        $bsc = Course::query()->where('code', 'SCH-12-COM')->first()
            ?? Course::query()->where('course_type', 'bsc')->first();

        if (! $diploma || ! $bsc) {
            $this->command?->error('Run CourseSeeder first.');

            return;
        }

        if ((float) $diploma->fee <= 0) {
            $diploma->update(['fee' => 45000]);
        }

        if ((float) $bsc->fee <= 0) {
            $bsc->update(['fee' => 120000]);
        }

        $enquiries = app(EnquiryService::class);

        $enquiries->create([
            'name' => 'Aarav Sharma',
            'father_name' => 'Mr Sharma',
            'mobile' => '9811000001',
            'gender' => Gender::Male->value,
            'course_id' => $diploma->id,
            'discussion_summary' => 'Walk-in — interested in 6 month diploma.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $enquiries->create([
            'name' => 'Priya Verma',
            'father_name' => 'Mrs Verma',
            'mobile' => '9811000002',
            'gender' => Gender::Female->value,
            'course_id' => $bsc->id,
            'discussion_summary' => 'Website enquiry for BSc programme.',
            'visit_status' => 'follow_up_required',
        ], $staff, LeadSource::Website);

        $enquiries->create([
            'name' => 'Karan Mehta',
            'mobile' => '9811000003',
            'gender' => Gender::Male->value,
            'course_id' => DefaultCourse::undecided()->id,
            'discussion_summary' => 'Quick walk-in, course not decided yet.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $admissionStudent = Student::query()->create([
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

        $nehaEnquiry = $enquiries->createForExistingStudent($admissionStudent, [
            'course_id' => $diploma->id,
            'discussion_summary' => 'Ready to proceed with admission.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $admissions = app(AdmissionService::class);
        $admission = $admissions->convert($nehaStudent, $nehaEnquiry, $staff, [
            'course_id' => $diploma->id,
            'discount_amount' => 5000,
        ]);

        $enrolledStudent = Student::query()->create([
            'name' => 'Rohit Kumar',
            'father_name' => 'Mr Kumar',
            'date_of_birth' => '2001-08-09',
            'gender' => Gender::Male,
            'mobile' => '9811000005',
            'email' => 'rohit.demo@example.com',
            'category' => StudentCategory::General,
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(StudentAuthService::class)->hashPortalPassword('09082001'),
        ]);

        $rohitEnquiry = $enquiries->createForExistingStudent($enrolledStudent, [
            'course_id' => $bsc->id,
            'discussion_summary' => 'Enrolled demo student with payment.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $rohitAdmission = $admissions->convert($enrolledStudent, $rohitEnquiry, $staff, [
            'course_id' => $bsc->id,
            'discount_amount' => 10000,
        ]);

        $rohitAdmission = $admissions->submitForm(
            $rohitAdmission,
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

        $enrollment = $admissions->approve($rohitAdmission, $staff);
        $feeStructure = $enrollment->feeStructure;

        $batch = Batch::query()->firstOrCreate(
            ['name' => 'Demo Batch — '.now()->format('M Y')],
            [
                'course_id' => $bsc->id,
                'trainer_user_id' => $staff->id,
                'start_date' => now()->subWeeks(2)->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'status' => BatchStatus::Active,
            ],
        );

        app(BatchService::class)->assign($enrolledStudent, $batch, $staff);

        if ($feeStructure) {
            app(PaymentService::class)->add(
                $feeStructure,
                $enrolledStudent,
                [
                    'payment_date' => now()->toDateString(),
                    'amount' => 15000,
                    'payment_mode' => 'cash',
                    'voucher_number' => 'DEMO-V001',
                ],
                UploadedFile::fake()->image('proof.jpg'),
                $staff,
            );
        }

        $this->command?->info('Demo CRM data seeded.');
        $this->command?->line('Staff login: demo@example.com / password');
        $this->command?->line('Enrolled student portal: 9811000005 / DOB password 09082001');
        $this->command?->warn('Admission demo (Neha Singh): 9811000004 — form not yet submitted.');
    }

    protected function demoStaff(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::query()->firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Staff',
                'password' => Hash::make('password'),
                'mobile' => '9999900000',
                'is_active' => true,
            ],
        );

        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}
