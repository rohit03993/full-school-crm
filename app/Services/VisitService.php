<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Support\CrmCacheInvalidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function add(Student $student, Enquiry $enquiry, array $data, User $staff): Visit
    {
        if ($enquiry->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'enquiry_id' => 'Enquiry does not belong to this student.',
            ]);
        }

        return DB::transaction(function () use ($student, $enquiry, $data, $staff): Visit {
            $status = VisitStatus::from($data['status']);

            $visit = Visit::query()->create([
                'student_id' => $student->id,
                'enquiry_id' => $enquiry->id,
                'visit_date' => $data['visit_date'],
                'staff_user_id' => $staff->id,
                'discussion_summary' => $data['discussion_summary'],
                'remarks' => $data['remarks'] ?? null,
                'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
                'status' => $status,
            ]);

            $enquiry->update(['latest_visit_status' => $status]);

            $this->audit->log(
                action: 'Visit Added',
                auditable: $visit,
                newValues: [
                    'student_id' => $student->id,
                    'enquiry_id' => $enquiry->id,
                    'status' => $status->value,
                ],
                user: $staff,
            );

            CrmCacheInvalidator::afterVisitOrFollowUpChange($enquiry->meeting_with_user_id);

            return $visit->load(['staff', 'enquiry.course']);
        });
    }
}
