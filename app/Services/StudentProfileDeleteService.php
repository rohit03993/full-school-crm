<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\ActivityAttendance;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\AttendancePunchWhatsappLog;
use App\Models\BatchStudent;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Enquiry;
use App\Models\FeePenalty;
use App\Models\FeeStructure;
use App\Models\HomeworkView;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentMarksheet;
use App\Models\User;
use App\Support\CrmCacheInvalidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentProfileDeleteService
{
    public function __construct(
        protected AuditService $audit,
        protected StorageCleanupService $storage,
    ) {}

    public function delete(Student $student, User $actor): void
    {
        if (! $actor->hasRole(RoleName::SuperAdmin->value)) {
            throw ValidationException::withMessages([
                'student' => 'Only Super Admin can permanently delete a profile.',
            ]);
        }

        DB::transaction(function () use ($student, $actor): void {
            $student = Student::query()
                ->with([
                    'enrollments.feeStructure.installments',
                    'enrollments.feeStructure.miscCharges',
                    'enrollments.feeStructure.discountEntries',
                    'enrollments.feeStructure.penalties',
                    'enrollments.feeStructure.history',
                    'admissions.documents',
                    'enquiries',
                ])
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

            $snapshot = [
                'student_id' => $student->id,
                'name' => $student->name,
                'mobile' => $student->mobile,
                'status' => $student->status?->value,
            ];

            $this->deletePayments($student);
            FeePenalty::query()->where('student_id', $student->id)->delete();

            foreach ($student->enrollments as $enrollment) {
                $this->deleteEnrollmentFees($enrollment);
                $this->storage->deleteStoredFile($enrollment->id_card_path);
                $enrollment->delete();
            }

            Admission::withTrashed()
                ->where('student_id', $student->id)
                ->with('documents')
                ->get()
                ->each(function (Admission $admission): void {
                    $admission->documents->each(function (Document $document): void {
                        $this->storage->deleteStoredFile($document->file_path);
                        $document->delete();
                    });

                    $admission->forceDelete();
                });

            Enquiry::withTrashed()
                ->where('student_id', $student->id)
                ->get()
                ->each(fn (Enquiry $enquiry) => $enquiry->forceDelete());

            ActivityAttendance::query()->where('student_id', $student->id)->delete();
            Attendance::query()->where('student_id', $student->id)->delete();
            BatchStudent::query()->where('student_id', $student->id)->delete();
            StudentMarksheet::query()->where('student_id', $student->id)->delete();
            HomeworkView::query()->where('student_id', $student->id)->delete();
            AttendancePunchWhatsappLog::query()->where('student_id', $student->id)->delete();

            $student->forceDelete();

            $this->audit->log(
                action: 'Student Profile Deleted',
                auditable: null,
                oldValues: $snapshot,
                user: $actor,
            );

            CrmCacheInvalidator::afterEnquiryChange();
            CrmCacheInvalidator::afterAdmissionChange();
            CrmCacheInvalidator::afterBulkImport();
        });
    }

    protected function deletePayments(Student $student): void
    {
        Payment::query()
            ->where('student_id', $student->id)
            ->orderBy('id')
            ->each(function (Payment $payment): void {
                $this->storage->deleteStoredFile($payment->proof_image_path);
                $this->storage->deleteStoredFile($payment->receipt_path);
                $payment->delete();
            });
    }

    protected function deleteEnrollmentFees(Enrollment $enrollment): void
    {
        $feeStructure = $enrollment->feeStructure;

        if (! $feeStructure) {
            return;
        }

        $this->deleteFeeStructureTree($feeStructure);
    }

    protected function deleteFeeStructureTree(FeeStructure $feeStructure): void
    {
        $feeStructure->penalties()->delete();
        $feeStructure->discountEntries()->delete();
        $feeStructure->miscCharges()->delete();
        $feeStructure->installments()->delete();
        $feeStructure->history()->delete();
        $feeStructure->delete();
    }
}
