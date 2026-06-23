<?php

namespace App\Services;

use App\Enums\LeadSource;
use App\Support\MeetingForOptions;
use App\Enums\NumberSequenceType;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Services\CustomFieldService;
use App\Support\CrmCacheInvalidator;
use App\Support\DefaultCourse;
use App\Support\SoftDeleteRecordGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnquiryService
{
    public function __construct(
        protected NumberGeneratorService $numberGenerator,
        protected StudentAuthService $studentAuth,
        protected AuditService $audit,
        protected CustomFieldService $customFields,
        protected StudentMobileService $mobiles,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $staff = null, LeadSource $leadSource = LeadSource::Website): Enquiry
    {
        return DB::transaction(function () use ($data, $staff, $leadSource): Enquiry {
            $student = $this->resolveStudent($data);

            return $this->storeEnquiry($student, $data, $staff, $leadSource);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForExistingStudent(Student $student, array $data, User $staff, LeadSource $leadSource): Enquiry
    {
        return DB::transaction(function () use ($student, $data, $staff, $leadSource): Enquiry {
            if ($student->status !== StudentStatus::Enrolled) {
                $student->update(['status' => StudentStatus::Enquiry]);
            }

            return $this->storeEnquiry($student, $data, $staff, $leadSource);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveStudent(array $data): Student
    {
        $mobile = $this->normalizeMobile($data['mobile']);
        $student = $this->mobiles->findStudentByNumber($mobile, restoreIfTrashed: true);

        $attributes = $this->studentAttributes($data, $student);
        $portalPassword = $student?->portal_password ?? $this->studentAuth->hashForNewStudent();

        if ($student) {
            $student->update([
                ...$attributes,
                'portal_password' => $portalPassword,
            ]);
        } else {
            $student = Student::query()->create([
                ...$attributes,
                'mobile' => $mobile,
                'portal_password' => $portalPassword,
                'status' => StudentStatus::Enquiry,
            ]);
        }

        if ($student->status !== StudentStatus::Enrolled) {
            $student->update(['status' => StudentStatus::Enquiry]);
        }

        return $student;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeEnquiry(Student $student, array $data, ?User $staff, LeadSource $leadSource): Enquiry
    {
        $this->ensureStudentCanReceiveEnquiry($student);

        $visitType = isset($data['visit_type'])
            ? VisitType::from($data['visit_type'])
            : VisitType::FirstVisit;

        $visitStatus = isset($data['visit_status'])
            ? VisitStatus::from($data['visit_status'])
            : VisitStatus::Interested;

        $meetingFor = MeetingForOptions::resolve($data['meeting_for'] ?? null);

        $courseId = $data['course_id'] ?? DefaultCourse::undecided()->id;

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => $this->numberGenerator->generate(NumberSequenceType::Enquiry),
            'course_id' => $courseId,
            'lead_source' => $leadSource,
            'meeting_with_user_id' => $data['meeting_with_user_id'] ?? null,
            'meeting_for' => $meetingFor,
            'visit_type' => $visitType,
            'follow_up_reason' => $visitType === VisitType::FollowUp
                ? ($data['follow_up_reason'] ?? null)
                : null,
            'latest_visit_status' => $visitStatus,
            'custom_data' => $this->resolveEnquiryCustomData($data),
        ]);

        $summary = $data['discussion_summary']
            ?? $data['message']
            ?? ($staff ? 'Quick walk-in enquiry.' : 'Online enquiry submitted via website.');

        Visit::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => $data['visit_date'] ?? now()->toDateString(),
            'staff_user_id' => $staff?->id,
            'discussion_summary' => $summary,
            'remarks' => $data['remarks'] ?? null,
            'status' => $visitStatus,
        ]);

        $this->audit->log(
            action: 'Enquiry Created',
            auditable: $enquiry,
            newValues: [
                'enquiry_number' => $enquiry->enquiry_number,
                'student_mobile' => $student->mobile,
                'course_id' => $enquiry->course_id,
                'source' => $leadSource->value,
            ],
            user: $staff,
        );

        if ($enquiry->meeting_with_user_id) {
            CrmCacheInvalidator::afterEnquiryChange($enquiry->meeting_with_user_id);
        } else {
            CrmCacheInvalidator::afterEnquiryChange();
        }

        return $enquiry->load(['student', 'course']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    protected function resolveEnquiryCustomData(array $data): ?array
    {
        if (! array_key_exists('custom_data', $data)) {
            return null;
        }

        $validated = $this->customFields->validateForEntity(
            CustomFieldService::ENTITY_ENQUIRY,
            is_array($data['custom_data']) ? $data['custom_data'] : [],
        );

        return $validated === [] ? null : $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function studentAttributes(array $data, ?Student $existing = null): array
    {
        return [
            'name' => $data['name'],
            'father_name' => $data['father_name'] ?? $existing?->father_name,
            'date_of_birth' => $data['date_of_birth'] ?? $existing?->date_of_birth,
            'gender' => $data['gender'] ?? $existing?->gender,
            'alternate_mobile' => $data['alternate_mobile'] ?? $existing?->alternate_mobile,
            'email' => $data['email'] ?? $existing?->email,
            'address' => $data['address'] ?? $existing?->address,
            'city' => $data['city'] ?? $existing?->city,
            'state' => $data['state'] ?? $existing?->state,
            'pincode' => $data['pincode'] ?? $existing?->pincode,
            'category' => $data['category'] ?? $existing?->category ?? 'general',
        ];
    }

    protected function normalizeMobile(string $mobile): string
    {
        $normalized = preg_replace('/\D/', '', $mobile);

        if (! preg_match('/^[6-9]\d{9}$/', $normalized)) {
            throw ValidationException::withMessages([
                'mobile' => 'Please enter a valid 10-digit Indian mobile number.',
            ]);
        }

        return $normalized;
    }

    protected function ensureStudentCanReceiveEnquiry(Student $student): void
    {
        SoftDeleteRecordGuard::ensureEnquirySlotAvailable($student);
    }
}
