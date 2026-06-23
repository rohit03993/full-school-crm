<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\NumberSequenceType;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Support\DefaultCourse;
use App\Support\SoftDeleteRecordGuard;
use Illuminate\Http\UploadedFile;
use App\Support\CrmCacheInvalidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdmissionService
{
    public function __construct(
        protected NumberGeneratorService $numberGenerator,
        protected DocumentService $documents,
        protected AuditService $audit,
        protected FeeStructureService $feeStructures,
        protected AdmissionFeePlanService $feePlans,
        protected StudentAuthService $studentAuth,
    ) {}

    /**
     * @param  array{
     *     course_id: int,
     *     discount_amount?: float|int|string|null,
     *     use_installment_plan?: bool|null,
     *     misc_fees?: array<int, array<string, mixed>>|null,
     *     installment_plan?: array<int, array<string, mixed>>|null,
     * }  $feeData
     */
    public function convert(Student $student, Enquiry $enquiry, User $staff, array $feeData): Admission
    {
        if ($enquiry->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'enquiry_id' => 'Enquiry does not belong to this student.',
            ]);
        }

        SoftDeleteRecordGuard::ensureEnquiryAdmissionSlotAvailable($enquiry);
        SoftDeleteRecordGuard::ensureAdmissionSlotAvailable($student);

        $course = $this->resolveAdmissionCourse((int) $feeData['course_id']);
        $discountAmount = max(0, (float) ($feeData['discount_amount'] ?? 0));
        $courseFee = (float) $course->fee;

        if ($courseFee <= 0) {
            throw ValidationException::withMessages([
                'course_id' => 'This course has no fee set. Update it in Courses admin before converting.',
            ]);
        }

        $miscFees = $this->feePlans->normalizeMiscFees($feeData['misc_fees'] ?? []);
        $miscTotal = round((float) collect($miscFees)->sum('amount'), 2);
        $useInstallmentPlan = (bool) ($feeData['use_installment_plan'] ?? false);
        $installmentPlan = $useInstallmentPlan
            ? $this->feePlans->normalizeInstallmentPlan($feeData['installment_plan'] ?? [])
            : [];
        $netFee = max(0, $courseFee - $discountAmount + $miscTotal);

        if ($discountAmount > $courseFee) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discount cannot be greater than the course fee.',
            ]);
        }

        if ($useInstallmentPlan) {
            $this->feePlans->assertInstallmentPlanValid($installmentPlan, $netFee);
        }

        if (
            Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->where('is_active', true)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'course_id' => 'Student already has an active enrollment for this course.',
            ]);
        }

        return DB::transaction(function () use (
            $student,
            $enquiry,
            $staff,
            $course,
            $courseFee,
            $discountAmount,
            $netFee,
            $useInstallmentPlan,
            $miscFees,
            $installmentPlan,
            $feeData,
        ): Admission {
            $enquiry->update(['course_id' => $course->id]);

            app(LeadAssignmentService::class)->clearCallingAssignment($enquiry);

            $admission = Admission::query()->create([
                'student_id' => $student->id,
                'enquiry_id' => $enquiry->id,
                'admission_number' => $this->numberGenerator->generate(NumberSequenceType::Admission),
                'course_fee' => $courseFee,
                'discount_amount' => 0,
                'net_fee' => 0,
                'use_installment_plan' => false,
                'status' => AdmissionStatus::Submitted,
            ]);

            $admission = $this->feePlans->sync($admission, $feeData, $staff);

            $student->update(['status' => StudentStatus::AdmissionSubmitted]);

            $this->audit->log(
                action: 'Converted to Admission',
                auditable: $admission,
                newValues: [
                    'admission_number' => $admission->admission_number,
                    'enquiry_id' => $enquiry->id,
                    'course_id' => $course->id,
                    'course_fee' => $courseFee,
                    'discount_amount' => $discountAmount,
                    'misc_fees_total' => round((float) collect($miscFees)->sum('amount'), 2),
                    'net_fee' => $netFee,
                    'use_installment_plan' => $useInstallmentPlan,
                ],
                user: $staff,
            );

            CrmCacheInvalidator::afterAdmissionChange($enquiry->meeting_with_user_id);

            return $admission->load(['enquiry.course', 'documents']);
        });
    }

    protected function resolveAdmissionCourse(int $courseId): Course
    {
        $course = Course::query()->find($courseId);

        if (! $course) {
            throw ValidationException::withMessages([
                'course_id' => 'Please select a valid course.',
            ]);
        }

        if ($course->code === DefaultCourse::UNDECIDED_CODE) {
            throw ValidationException::withMessages([
                'course_id' => 'Course is required. “Course Not Decided” cannot be used for admission.',
            ]);
        }

        return $course;
    }

    /**
     * @param  array{
     *     discount_amount?: float|int|string|null,
     *     use_installment_plan?: bool|null,
     *     misc_fees?: array<int, array<string, mixed>>|null,
     *     installment_plan?: array<int, array<string, mixed>>|null,
     * }  $data
     */
    public function updateFeePlan(Admission $admission, array $data, ?User $staff = null): Admission
    {
        return $this->feePlans->sync($admission, $data, $staff);
    }

    public function updateFees(Admission $admission, float $discountAmount, ?User $staff = null): Admission
    {
        $admission->loadMissing(['miscFees', 'installmentPlans']);

        return $this->updateFeePlan($admission, [
            'discount_amount' => $discountAmount,
            'use_installment_plan' => $admission->use_installment_plan,
            'misc_fees' => $admission->miscFees->map(fn ($row) => [
                'label' => $row->label,
                'amount' => $row->amount,
            ])->all(),
            'installment_plan' => $admission->installmentPlans->map(fn ($row) => [
                'label' => $row->label,
                'amount' => $row->amount,
                'due_date' => $row->due_date?->toDateString(),
            ])->all(),
        ], $staff);
    }

    /**
     * @param  array<string, mixed>  $academicData
     * @param  array<string, UploadedFile|null>  $uploads
     */
    public function submitForm(Admission $admission, array $academicData, array $uploads, ?User $staff = null, ?float $discountAmount = null): Admission
    {
        if (! $admission->isEditable()) {
            throw ValidationException::withMessages([
                'admission' => 'This admission form can no longer be edited.',
            ]);
        }

        return DB::transaction(function () use ($admission, $academicData, $uploads, $staff, $discountAmount): Admission {
            if ($discountAmount !== null && $admission->canAdjustFees()) {
                $admission = $this->updateFees($admission, $discountAmount, $staff);
            }
            $admission->update([
                'tenth_board' => $academicData['tenth_board'] ?? null,
                'tenth_percentage' => $academicData['tenth_percentage'] ?? null,
                'twelfth_board' => $academicData['twelfth_board'] ?? null,
                'twelfth_percentage' => $academicData['twelfth_percentage'] ?? null,
                'graduation' => $academicData['graduation'] ?? null,
                'graduation_percentage' => $academicData['graduation_percentage'] ?? null,
            ]);

            foreach ($uploads as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $this->documents->store($admission, \App\Enums\DocumentType::from($type), $file, $staff);
                }
            }

            $admission->refresh()->load('documents');

            if (! $this->documents->hasRequiredDocuments($admission)) {
                throw ValidationException::withMessages([
                    'documents' => 'Please upload all required documents: photo, Aadhaar, marksheet, and signature.',
                ]);
            }

            $admission->update([
                'status' => AdmissionStatus::VerificationPending,
                'submitted_at' => now(),
                'staff_remarks' => null,
            ]);

            $admission->student->update(['status' => StudentStatus::VerificationPending]);

            $this->audit->log(
                action: 'Admission Form Submitted',
                auditable: $admission,
                newValues: ['admission_number' => $admission->admission_number],
                user: $staff,
            );

            CrmCacheInvalidator::afterAdmissionChange($admission->enquiry?->meeting_with_user_id);

            return $admission->fresh(['enquiry.course', 'documents', 'student']);
        });
    }

    public function approve(Admission $admission, User $staff): Enrollment
    {
        Gate::forUser($staff)->authorize('approve', $admission);

        if (! $admission->canBeApproved()) {
            throw ValidationException::withMessages([
                'admission' => 'Only admissions pending verification can be approved.',
            ]);
        }

        if (
            Enrollment::query()
                ->where('student_id', $admission->student_id)
                ->where('is_active', true)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'admission' => 'Student already has an active enrollment.',
            ]);
        }

        return DB::transaction(function () use ($admission, $staff): Enrollment {
            $locked = Admission::query()
                ->whereKey($admission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing(['enquiry', 'student']);

            if ($locked->status !== AdmissionStatus::VerificationPending) {
                throw ValidationException::withMessages([
                    'admission' => 'Only admissions pending verification can be approved.',
                ]);
            }

            if (Enrollment::query()->where('admission_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'admission' => 'This admission has already been approved.',
                ]);
            }

            if (
                Enrollment::query()
                    ->where('student_id', $locked->student_id)
                    ->where('is_active', true)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'admission' => 'Student already has an active enrollment.',
                ]);
            }

            $locked->update([
                'status' => AdmissionStatus::Approved,
                'approved_by_user_id' => $staff->id,
                'approved_at' => now(),
            ]);

            Enrollment::query()
                ->where('student_id', $locked->student_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $enrollment = Enrollment::query()->create([
                'student_id' => $locked->student_id,
                'admission_id' => $locked->id,
                'course_id' => $locked->enquiry->course_id,
                'academic_session_id' => AcademicSession::current()?->id,
                'enrollment_number' => $this->numberGenerator->generate(NumberSequenceType::Enrollment),
                'enrolled_at' => now(),
                'status' => EnrollmentStatus::Enrolled,
                'is_active' => true,
            ]);

            $locked->student->update(['status' => StudentStatus::Enrolled]);

            if ($locked->enquiry) {
                app(LeadAssignmentService::class)->clearCallingAssignment($locked->enquiry);
            }

            $this->studentAuth->ensurePortalLoginForStudent($locked->student);

            $this->feeStructures->createFromAdmission($enrollment, $locked, $staff);

            $this->audit->log(
                action: 'Admission Approved, Enrollment Generated',
                auditable: $enrollment,
                newValues: [
                    'enrollment_number' => $enrollment->enrollment_number,
                    'admission_number' => $locked->admission_number,
                    'academic_session_id' => $enrollment->academic_session_id,
                ],
                user: $staff,
            );

            if ($locked->enquiry?->meeting_with_user_id) {
                CrmCacheInvalidator::afterAdmissionChange($locked->enquiry->meeting_with_user_id);
            } else {
                CrmCacheInvalidator::afterAdmissionChange();
            }

            return $enrollment->load(['course', 'admission', 'feeStructure']);
        });
    }

    public function returnForCorrection(Admission $admission, User $staff, string $remarks): Admission
    {
        Gate::forUser($staff)->authorize('returnForCorrection', $admission);

        if ($admission->status !== AdmissionStatus::VerificationPending) {
            throw ValidationException::withMessages([
                'admission' => 'Only pending admissions can be returned for correction.',
            ]);
        }

        return DB::transaction(function () use ($admission, $staff, $remarks): Admission {
            $admission->update([
                'status' => AdmissionStatus::Submitted,
                'staff_remarks' => $remarks,
                'submitted_at' => null,
            ]);

            $admission->student->update(['status' => StudentStatus::AdmissionSubmitted]);

            $this->audit->log(
                action: 'Admission Returned for Correction',
                auditable: $admission,
                newValues: ['remarks' => $remarks],
                user: $staff,
            );

            CrmCacheInvalidator::afterAdmissionChange($admission->enquiry?->meeting_with_user_id);

            return $admission->fresh(['enquiry.course', 'documents']);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, Enquiry>
     */
    public function convertibleEnquiries(Student $student): \Illuminate\Support\Collection
    {
        if (Admission::query()->where('student_id', $student->id)->exists()) {
            return collect();
        }

        return $student->enquiries()
            ->with('course')
            ->whereDoesntHave('admission')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->reject(fn (Enquiry $enquiry): bool => Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_id', $enquiry->course_id)
                ->where('is_active', true)
                ->exists())
            ->values();
    }
}
