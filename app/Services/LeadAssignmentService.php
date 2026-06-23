<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\CrmCacheInvalidator;
use Illuminate\Validation\ValidationException;

class LeadAssignmentService
{
    public function assignForCalling(Enquiry $enquiry, User $staff, User $assignedBy): Enquiry
    {
        if (! $staff->is_active) {
            throw ValidationException::withMessages([
                'staff_user_id' => 'Selected staff account is inactive.',
            ]);
        }

        $previousStaffId = $enquiry->meeting_with_user_id;

        $enquiry->update([
            'meeting_with_user_id' => $staff->id,
            'calling_assigned_at' => now(),
            'calling_assigned_by_user_id' => $assignedBy->id,
        ]);

        $this->flushCaches($previousStaffId, $staff->id);

        return $enquiry->fresh(['meetingWith', 'callingAssignedBy']);
    }

    public function clearCallingAssignment(Enquiry $enquiry): Enquiry
    {
        $previousStaffId = $enquiry->meeting_with_user_id;

        if ($previousStaffId === null && $enquiry->calling_assigned_at === null) {
            return $enquiry;
        }

        $enquiry->update([
            'meeting_with_user_id' => null,
            'calling_assigned_at' => null,
            'calling_assigned_by_user_id' => null,
        ]);

        $this->flushCaches($previousStaffId);

        return $enquiry->fresh();
    }

    /**
     * @param  iterable<int, Enquiry>  $enquiries
     */
    public function assignManyForCalling(iterable $enquiries, User $staff, User $assignedBy): int
    {
        if (! $staff->is_active) {
            throw ValidationException::withMessages([
                'staff_user_id' => 'Selected staff account is inactive.',
            ]);
        }

        $count = 0;

        foreach ($enquiries as $enquiry) {
            if (! $enquiry instanceof Enquiry) {
                continue;
            }

            $this->assignForCalling($enquiry, $staff, $assignedBy);
            $count++;
        }

        return $count;
    }

    /**
     * @param  iterable<int, Enquiry>  $enquiries
     */
    public function clearManyCallingAssignments(iterable $enquiries): int
    {
        $count = 0;

        foreach ($enquiries as $enquiry) {
            if (! $enquiry instanceof Enquiry) {
                continue;
            }

            if ($enquiry->calling_assigned_at === null) {
                continue;
            }

            $this->clearCallingAssignment($enquiry);
            $count++;
        }

        return $count;
    }

    /**
     * Latest admin calling assignment on this student (for profile banner).
     *
     * @return array{staff_name: string, assigned_at: \Illuminate\Support\Carbon, is_mine: bool}|null
     */
    public function profileCallingAssignment(Student $student, ?User $viewer): ?array
    {
        $enquiry = $student->enquiries()
            ->whereNotNull('calling_assigned_at')
            ->with('meetingWith')
            ->orderByDesc('calling_assigned_at')
            ->first();

        if (! $enquiry) {
            return null;
        }

        $isMine = $viewer && $enquiry->meeting_with_user_id === $viewer->id;
        $isAdmin = $viewer && CrmAccess::can($viewer, CrmPermission::LeadsViewAll);

        if (! $isMine && ! $isAdmin) {
            return null;
        }

        return [
            'staff_name' => $enquiry->meetingWith?->name ?? 'Staff',
            'assigned_at' => $enquiry->calling_assigned_at,
            'is_mine' => $isMine,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function activeStaffOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function flushCaches(?int ...$staffUserIds): void
    {
        foreach (array_filter($staffUserIds) as $staffUserId) {
            MyLeadsService::flushStatsCache($staffUserId);
            CrmCacheInvalidator::afterEnquiryChange($staffUserId);
        }
    }
}
