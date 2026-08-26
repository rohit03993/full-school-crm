<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatus;
use App\Enums\BatchShift;
use App\Enums\BatchStatus;
use App\Enums\CampusVisitPurpose;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\InstituteType;
use App\Enums\LeadSource;
use App\Enums\NumberSequenceType;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentCategory;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\AcademicSession;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\HomeworkAssignment;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentCase;
use App\Models\User;
use App\Models\Visit;
use App\Services\ActivityAttendanceService;
use App\Services\BatchService;
use App\Services\BatchSubjectService;
use App\Services\FeeStructureService;
use App\Services\HomeworkAssignmentService;
use App\Services\NumberGeneratorService;
use App\Services\Punch\ManualStaffAttendanceService;
use App\Services\StudentAuthService;
use App\Services\StudentCaseService;
use App\Support\InstituteProfile;
use App\Support\MeetingForOptions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Rich school campus for sales demo: Classes 5–12 × sections A/B,
 * ~35 staff (teachers + office roles), subjects, homework, marks, cases.
 * Re-runnable: if SCH-05 exists, finishes homework / marks / cases / attendance only.
 */
class DemoSchoolCampusSeeder extends Seeder
{
    /** Students per section (Class N-A / Class N-B). */
    public const STUDENTS_PER_SECTION = 23;

    /** @var list<User> */
    protected array $teachers = [];

    protected ?User $academicCoordinator = null;

    protected ?User $counsellor = null;

    protected ?User $accountant = null;

    protected int $studentMobileSeq = 9822000100;

    protected int $rollSeq = 501;

    public function run(): void
    {
        if (InstituteProfile::type() !== InstituteType::School) {
            $this->command?->warn('DemoSchoolCampusSeeder skipped — institute type is not school.');

            return;
        }

        $session = AcademicSession::current();
        $approver = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::SuperAdmin->value))
            ->first();

        if (! $session || ! $approver) {
            $this->command?->error('Need AcademicSession + Super Admin before campus seed.');

            return;
        }

        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $office = $this->seedOfficeStaff();
        $this->teachers = $this->seedTeachers(26);
        $this->academicCoordinator = $office['academic'];
        $this->counsellor = $office['counsellor'];
        $this->accountant = $office['accountant'];

        if (Course::query()->where('code', 'SCH-05')->exists()) {
            $this->command?->warn('School campus classes already exist — finishing homework, marks, cases, attendance…');
            $batchesWithStudents = $this->loadExistingCampusGroups($session);
            $this->seedHomeworkAndMarks($batchesWithStudents);
            $this->seedCases($batchesWithStudents, $approver);
            $this->seedStaffAttendanceToday($approver);
            $this->printCampusSummary();

            return;
        }

        $this->command?->info('Seeding school campus (Classes 5–12)…');

        $staffActor = $office['admission'];
        $batchesWithStudents = [];

        foreach (range(5, 12) as $classNo) {
            $course = $this->ensureClassCourse($classNo);
            $subjectNames = $this->subjectsForClass($classNo);

            foreach (['A', 'B'] as $section) {
                $lead = $this->teachers[($classNo + ord($section)) % count($this->teachers)];
                $batch = Batch::query()->create([
                    'name' => "Class {$classNo}-{$section}",
                    'course_id' => $course->id,
                    'academic_session_id' => $session->id,
                    'section' => $section,
                    'shift' => BatchShift::Morning,
                    'trainer_user_id' => $lead->id,
                    'start_date' => $session->starts_on->toDateString(),
                    'end_date' => $session->ends_on->toDateString(),
                    'status' => BatchStatus::Active,
                ]);

                $subjectRows = [];
                foreach ($subjectNames as $i => $subjectName) {
                    $teacher = $this->teachers[($classNo * 3 + $i + ord($section)) % count($this->teachers)];
                    $subjectRows[] = [
                        'name' => $subjectName,
                        'default_max_marks' => 100,
                        'user_id' => $teacher->id,
                    ];
                }
                app(BatchSubjectService::class)->sync($batch, $subjectRows, $lead->id);

                $perSection = self::STUDENTS_PER_SECTION;
                $students = [];
                for ($n = 1; $n <= $perSection; $n++) {
                    $students[] = $this->enrollStudentFast(
                        staff: $staffActor,
                        session: $session,
                        course: $course,
                        batch: $batch,
                        classNo: $classNo,
                        section: $section,
                        index: $n,
                    );
                }

                $batchesWithStudents[] = ['batch' => $batch, 'students' => $students, 'class' => $classNo];
                $this->command?->line("  {$batch->name}: {$perSection} students");
            }
        }

        $this->seedHomeworkAndMarks($batchesWithStudents);
        $this->seedCases($batchesWithStudents, $approver);
        $this->seedStaffAttendanceToday($approver);

        $this->printCampusSummary();
    }

    /**
     * @return list<array{batch: Batch, students: list<Student>, class: int}>
     */
    protected function loadExistingCampusGroups(AcademicSession $session): array
    {
        $groups = [];

        foreach (range(5, 12) as $classNo) {
            $course = Course::query()->where('code', sprintf('SCH-%02d', $classNo))->first();
            if (! $course) {
                continue;
            }

            foreach (['A', 'B'] as $section) {
                $batch = Batch::query()
                    ->where('course_id', $course->id)
                    ->where('academic_session_id', $session->id)
                    ->where('name', "Class {$classNo}-{$section}")
                    ->first();

                if (! $batch) {
                    continue;
                }

                $studentIds = $batch->activeStudents()->pluck('student_id');
                $students = Student::query()
                    ->whereIn('id', $studentIds)
                    ->with('activeEnrollment')
                    ->get()
                    ->all();

                $groups[] = ['batch' => $batch, 'students' => $students, 'class' => $classNo];
                $this->command?->line("  Resume {$batch->name}: ".count($students).' students');
            }
        }

        return $groups;
    }

    /**
     * @return array{academic: User, counsellor: User, admission: User, accountant: User, fee: User, messaging: User}
     */
    protected function seedOfficeStaff(): array
    {
        $defs = [
            'academic' => ['Academic Coordinator', 'academic.coord@example.com', '9900100001', [StaffJobRole::AcademicCoordinator]],
            'counsellor' => ['Riya Counsellor', 'counsellor01@example.com', '9900100002', [StaffJobRole::Counsellor]],
            'counsellor2' => ['Karan Counsellor', 'counsellor02@example.com', '9900100003', [StaffJobRole::Counsellor]],
            'admission' => ['Neha Admission', 'admission01@example.com', '9900100004', [StaffJobRole::AdmissionOfficer]],
            'admission2' => ['Vikas Admission', 'admission02@example.com', '9900100005', [StaffJobRole::AdmissionOfficer]],
            'accountant' => ['Pooja Accountant', 'accounts01@example.com', '9900100006', [StaffJobRole::Accountant]],
            'accountant2' => ['Rahul Accounts', 'accounts02@example.com', '9900100007', [StaffJobRole::Accountant]],
            'fee' => ['Sonia Fee Adjuster', 'fees01@example.com', '9900100008', [StaffJobRole::FeeAdjuster]],
            'messaging' => ['Amit Messaging', 'messaging01@example.com', '9900100009', [StaffJobRole::MessagingCoordinator]],
        ];

        $users = [];
        $code = 100;
        foreach ($defs as $key => [$name, $email, $mobile, $roles]) {
            $users[$key] = $this->makeStaff($name, $email, $mobile, $roles, 'STF'.$code, $name);
            $code++;
        }

        return [
            'academic' => $users['academic'],
            'counsellor' => $users['counsellor'],
            'admission' => $users['admission'],
            'accountant' => $users['accountant'],
            'fee' => $users['fee'],
            'messaging' => $users['messaging'],
        ];
    }

    /**
     * @return list<User>
     */
    protected function seedTeachers(int $count): array
    {
        $first = ['Anil', 'Sunita', 'Meena', 'Rajesh', 'Kavita', 'Deepak', 'Nisha', 'Suresh', 'Priya', 'Manoj', 'Geeta', 'Vivek', 'Anita', 'Ramesh', 'Shweta', 'Ajay', 'Neelam', 'Sanjay', 'Komal', 'Ashok', 'Divya', 'Nitin', 'Poonam', 'Gaurav', 'Seema', 'Yogesh'];
        $last = ['Sharma', 'Verma', 'Gupta', 'Singh', 'Patel', 'Joshi', 'Nair', 'Khan', 'Reddy', 'Iyer'];

        $teachers = [];
        for ($i = 1; $i <= $count; $i++) {
            $name = $first[($i - 1) % count($first)].' '.$last[($i - 1) % count($last)];
            $email = sprintf('teacher%02d@example.com', $i);
            $mobile = sprintf('99002%05d', $i);
            $teachers[] = $this->makeStaff(
                $name,
                $email,
                $mobile,
                [StaffJobRole::Teacher],
                sprintf('TCH%03d', $i),
                'Teacher',
            );
        }

        return $teachers;
    }

    /**
     * @param  list<StaffJobRole>  $jobRoles
     */
    protected function makeStaff(
        string $name,
        string $email,
        string $mobile,
        array $jobRoles,
        string $employeeCode,
        string $designation,
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'mobile' => $mobile,
                'is_active' => true,
            ],
        );

        $roleNames = array_merge(
            [RoleName::Staff->value],
            array_map(fn (StaffJobRole $r): string => $r->value, $jobRoles),
        );
        $user->syncRoles($roleNames);

        StaffProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_code' => $employeeCode,
                'designation' => $designation,
                'mobile' => $mobile,
            ],
        );

        return $user;
    }

    protected function ensureClassCourse(int $classNo): Course
    {
        $fee = match (true) {
            $classNo <= 8 => 45000,
            $classNo <= 10 => 60000,
            default => 90000,
        };

        return Course::query()->updateOrCreate(
            ['code' => sprintf('SCH-%02d', $classNo)],
            [
                'name' => "Class {$classNo}",
                'programme_category' => 'school',
                'duration' => 1,
                'duration_type' => 'years',
                'fee' => $fee,
                'status' => CourseStatus::Active,
                'show_on_website' => true,
            ],
        );
    }

    /**
     * @return list<string>
     */
    protected function subjectsForClass(int $classNo): array
    {
        if ($classNo >= 11) {
            return ['English', 'Physics', 'Chemistry', 'Mathematics', 'Computer Science'];
        }

        return ['English', 'Hindi', 'Mathematics', 'Science', 'Social Science'];
    }

    protected function enrollStudentFast(
        User $staff,
        AcademicSession $session,
        Course $course,
        Batch $batch,
        int $classNo,
        string $section,
        int $index,
    ): Student {
        $this->studentMobileSeq++;
        $mobile = (string) $this->studentMobileSeq;
        $gender = $index % 2 === 0 ? Gender::Female : Gender::Male;
        $boys = ['Aarav', 'Kabir', 'Reyansh', 'Vivaan', 'Advait', 'Ishaan', 'Shaurya', 'Atharv', 'Dhruv', 'Arjun', 'Krish', 'Dev', 'Rohan', 'Yash', 'Om'];
        $girls = ['Ananya', 'Isha', 'Diya', 'Myra', 'Sara', 'Aanya', 'Kiara', 'Pari', 'Anvi', 'Navya', 'Riya', 'Saanvi', 'Khushi', 'Meera', 'Tara'];
        $last = ['Malhotra', 'Kapoor', 'Bansal', 'Mehta', 'Chawla', 'Saxena', 'Ahuja', 'Bhatia', 'Chopra', 'Gill'];
        $first = $gender === Gender::Female
            ? $girls[($index - 1) % count($girls)]
            : $boys[($index - 1) % count($boys)];
        $surname = $last[($classNo + $index + ord($section)) % count($last)];
        $name = "{$first} {$surname}";
        $year = 2013 - ($classNo - 5);
        $dob = sprintf('%04d-%02d-%02d', $year, (($index - 1) % 12) + 1, (($index * 2) % 27) + 1);

        $student = Student::query()->create([
            'name' => $name,
            'father_name' => 'Mr '.$surname,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'mobile' => $mobile,
            'email' => Str::slug($name).".c{$classNo}{$section}{$index}@demo.local",
            'category' => StudentCategory::General,
            'status' => StudentStatus::Enrolled,
            'portal_password' => app(StudentAuthService::class)->hashPortalPassword(
                str_replace('-', '', date('dmY', strtotime($dob))),
            ),
        ]);

        $numbers = app(NumberGeneratorService::class);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => $numbers->generate(NumberSequenceType::Enquiry),
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => MeetingForOptions::defaultValue(),
            'visit_type' => VisitType::FirstVisit,
            'latest_visit_status' => VisitStatus::Joined,
            'meeting_with_user_id' => null,
        ]);

        Visit::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => now()->subDays(($index % 10) + 1)->toDateString(),
            'staff_user_id' => $staff->id,
            'discussion_summary' => "Campus demo enroll — Class {$classNo}-{$section}.",
            'remarks' => 'Seeded for sales demo (not a live lead).',
            'status' => VisitStatus::Joined,
        ]);

        $courseFee = (float) $course->fee;
        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => $numbers->generate(NumberSequenceType::Admission),
            'course_fee' => $courseFee,
            'discount_amount' => 0,
            'net_fee' => $courseFee,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_by_user_id' => $staff->id,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => (string) $this->rollSeq,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);
        $this->rollSeq++;

        app(FeeStructureService::class)->createFromAdmission($enrollment, $admission, $staff);
        app(BatchService::class)->assign($student, $batch, $staff);
        app(StudentAuthService::class)->ensurePortalLoginForStudent($student);

        return $student->fresh(['activeEnrollment']);
    }

    /**
     * @param  list<array{batch: Batch, students: list<Student>, class: int}>  $groups
     */
    protected function seedHomeworkAndMarks(array $groups): void
    {
        $homework = app(HomeworkAssignmentService::class);
        $examType = ActivityType::query()->where('slug', 'exam')->first();
        $attendance = app(ActivityAttendanceService::class);

        foreach ($groups as $group) {
            $batch = $group['batch'];
            $students = $group['students'];
            $teacher = $batch->trainer_user_id
                ? User::query()->find($batch->trainer_user_id)
                : ($this->teachers[0] ?? null);

            if (! $teacher) {
                continue;
            }

            $activeTitle = 'This week — '.$batch->name.' practice';
            $pastTitle = 'Last fortnight revision — '.$batch->name;

            if (! HomeworkAssignment::query()->where('batch_id', $batch->id)->where('title', $activeTitle)->exists()) {
                $active = $homework->create($teacher, [
                    'batch_id' => $batch->id,
                    'title' => $activeTitle,
                    'description' => 'Complete the worksheet and bring it tomorrow.',
                    'send_whatsapp' => false,
                ]);
                $active->update([
                    'homework_date' => now()->toDateString(),
                ]);
            }

            if (! HomeworkAssignment::query()->where('batch_id', $batch->id)->where('title', $pastTitle)->exists()) {
                $past = $homework->create($teacher, [
                    'batch_id' => $batch->id,
                    'title' => $pastTitle,
                    'description' => 'Revision chapter already discussed in class.',
                    'send_whatsapp' => false,
                ]);
                $past->update([
                    'published_at' => now()->subDays(12),
                    'homework_date' => now()->subDays(12)->toDateString(),
                ]);
            }

            if (! $examType || $students === [] || $group['class'] < 10) {
                continue;
            }

            $batch->loadMissing('activeSubjects');
            foreach ($batch->activeSubjects->take(3) as $subject) {
                $testKey = 'unit-'.$batch->id.'-'.$subject->id;
                $session = ActivitySession::query()
                    ->where('batch_id', $batch->id)
                    ->where('activity_type_id', $examType->id)
                    ->where('title', "Unit Test — {$subject->name}")
                    ->first();

                if (! $session) {
                    $session = ActivitySession::query()->create([
                        'activity_type_id' => $examType->id,
                        'title' => "Unit Test — {$subject->name}",
                        'session_date' => now()->subDays(5)->toDateString(),
                        'batch_id' => $batch->id,
                        'created_by_user_id' => $teacher->id,
                        'metadata' => [
                            'test_key' => $testKey,
                            'test_name' => 'Unit Test 1',
                            'subject' => $subject->name,
                            'max_marks' => 40,
                        ],
                    ]);
                }

                if ($session->activityAttendances()->exists()) {
                    continue;
                }

                $scores = [];
                foreach ($students as $i => $student) {
                    // Keep within max_marks (40): spread ~24–38 across the section.
                    $scores[$student->id] = 24 + ($i % 15);
                }
                $attendance->importStudentScores($session, $scores, $teacher);
            }
        }
    }

    /**
     * @param  list<array{batch: Batch, students: list<Student>, class: int}>  $groups
     */
    protected function seedCases(array $groups, User $openedBy): void
    {
        if (! $this->counsellor || ! $this->accountant || ! $this->academicCoordinator) {
            return;
        }

        $cases = app(StudentCaseService::class);
        $picked = [];

        foreach ($groups as $group) {
            if ($group['class'] < 11) {
                continue;
            }
            foreach ($group['students'] as $student) {
                $picked[] = $student;
            }
        }

        if (count($picked) < 3) {
            return;
        }

        $demoTitles = [
            'Late fee waiver request',
            'Extra class for Maths',
            'TC / bonafide pending',
        ];

        if (StudentCase::query()->whereIn('title', $demoTitles)->exists()) {
            return;
        }

        $cases->open(
            $picked[0],
            CampusVisitPurpose::Fees,
            $demoTitles[0],
            'Parent asked to waive late fee for Term 1.',
            $this->accountant,
            $this->counsellor,
            'Please review and advise.',
        );

        $cases->open(
            $picked[1],
            CampusVisitPurpose::Academic,
            $demoTitles[1],
            'Student needs remedial Maths sessions.',
            $this->academicCoordinator,
            $this->counsellor,
            'Assign a teacher for two weeks.',
        );

        $cases->open(
            $picked[2],
            CampusVisitPurpose::Documents,
            $demoTitles[2],
            'Documents requested for scholarship.',
            $this->academicCoordinator,
            $openedBy,
            null,
        );
    }

    protected function seedStaffAttendanceToday(User $actor): void
    {
        $manual = app(ManualStaffAttendanceService::class);
        $today = now()->toDateString();

        foreach (array_slice($this->teachers, 0, 8) as $teacher) {
            try {
                $manual->manualIn($teacher, $today, $actor);
            } catch (\Throwable) {
                // ignore punch window conflicts
            }
        }
    }

    protected function printCampusSummary(): void
    {
        $this->command?->newLine();
        $this->command?->info('=== School campus demo ready ===');
        $sections = 8 * 2;
        $students = $sections * self::STUDENTS_PER_SECTION;
        $this->command?->line("Classes 5–12 × sections A/B = {$sections} sections · {$students} students (fake mobiles 9822…).");
        $this->command?->line('Subjects + teachers assigned on every section.');
        $this->command?->line('Staff password: password');
        $this->command?->line('Teachers: teacher01@example.com … teacher26@example.com');
        $this->command?->line('Office: counsellor01@example.com · admission01@example.com · accounts01@example.com · academic.coord@example.com');
        $this->command?->line('Homework: active + past per section · marks on Class 10–12');
        $this->command?->line('Open cases: 3 · Staff IN today: 8 teachers');
    }
}
