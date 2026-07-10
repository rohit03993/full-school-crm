<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\CrmCacheInvalidator;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class LeadAssignmentService
{
    public function assignForCalling(
        Enquiry $enquiry,
        User $staff,
        User $assignedBy,
        ?string $handoffNote = null,
        bool $requireHandoffNote = true,
    ): Enquiry {
        if (! $staff->is_active) {
            throw ValidationException::withMessages([
                'staff_user_id' => 'Selected staff account is inactive.',
            ]);
        }

        $handoffNote = filled($handoffNote) ? trim($handoffNote) : null;
        $isReassign = $enquiry->calling_assigned_at !== null
            || ($enquiry->meeting_with_user_id !== null && $enquiry->meeting_with_user_id !== $staff->id);

        if ($requireHandoffNote && $isReassign && blank($handoffNote)) {
            throw ValidationException::withMessages([
                'calling_handoff_note' => 'Add a handoff note when assigning or reassigning for calling.',
            ]);
        }

        $previousStaffId = $enquiry->meeting_with_user_id;

        $enquiry->update([
            'meeting_with_user_id' => $staff->id,
            'calling_assigned_at' => now(),
            'calling_assigned_by_user_id' => $assignedBy->id,
            'calling_handoff_note' => $handoffNote ?? $enquiry->calling_handoff_note,
        ]);

        $this->notifyAssignedStaff($enquiry->fresh(['student']), $staff, $assignedBy);
        $this->flushCaches($previousStaffId, $staff->id);

        return $enquiry->fresh(['meetingWith', 'callingAssignedBy', 'student']);
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
            'calling_handoff_note' => null,
        ]);

        $this->flushCaches($previousStaffId);

        return $enquiry->fresh();
    }

    /**
     * @param  iterable<int, Enquiry>  $enquiries
     */
    public function assignManyForCalling(
        iterable $enquiries,
        User $staff,
        User $assignedBy,
        ?string $handoffNote = null,
    ): int {
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

            $this->assignForCalling($enquiry, $staff, $assignedBy, $handoffNote);
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
     * Latest calling assignment on this student (for profile banner).
     *
     * @return array{
     *     staff_name: string,
     *     assigned_by_name: string,
     *     assigned_at: \Illuminate\Support\Carbon,
     *     handoff_note: ?string,
     *     is_mine: bool,
     * }|null
     */
    public function profileCallingAssignment(Student $student, ?User $viewer): ?array
    {
        $enquiry = $student->enquiries()
            ->whereNotNull('calling_assigned_at')
            ->with(['meetingWith', 'callingAssignedBy'])
            ->orderByDesc('calling_assigned_at')
            ->first();

        if (! $enquiry) {
            return null;
        }

        $isMine = $viewer && $enquiry->meeting_with_user_id === $viewer->id;
        $isAssigner = $viewer && $enquiry->calling_assigned_by_user_id === $viewer->id;
        $isAdmin = $viewer && CrmAccess::can($viewer, CrmPermission::LeadsViewAll);

        if (! $isMine && ! $isAssigner && ! $isAdmin) {
            return null;
        }

        return [
            'staff_name' => $enquiry->meetingWith?->name ?? 'Staff',
            'assigned_by_name' => $enquiry->callingAssignedBy?->name ?? 'Staff',
            'assigned_at' => $enquiry->calling_assigned_at,
            'handoff_note' => $enquiry->calling_handoff_note,
            'is_mine' => $isMine,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function activeStaffOptions(): array
    {
        return \App\Support\StaffOptions::assignableStaffOptions();
    }

    protected function notifyAssignedStaff(Enquiry $enquiry, User $staff, User $assignedBy): void
    {
        if ($staff->id === $assignedBy->id) {
            return;
        }

        $enquiry->loadMissing('student');

        $studentName = $enquiry->student?->name ?? 'Lead';
        $profileUrl = $enquiry->student_id
            ? StudentProfilePage::getUrl(['record' => $enquiry->student_id])
            : null;

        $notification = Notification::make()
            ->title('Lead assigned for calling')
            ->body("{$assignedBy->name} assigned {$studentName} to you for telecalling.");

        if ($profileUrl) {
            $notification->actions([
                \Filament\Actions\Action::make('view')
                    ->label('Open profile')
                    ->url($profileUrl),
            ]);
        }

        $notification->sendToDatabase($staff);
    }

    protected function flushCaches(?int ...$staffUserIds): void
    {
        foreach (array_filter($staffUserIds) as $staffUserId) {
            MyLeadsService::flushStatsCache($staffUserId);
            CrmCacheInvalidator::afterEnquiryChange($staffUserId);
        }
    }
}
