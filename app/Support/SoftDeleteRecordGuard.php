<?php

namespace App\Support;

use App\Models\Admission;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\Visit;
use Illuminate\Validation\ValidationException;

class SoftDeleteRecordGuard
{
    public static function ensureEnquirySlotAvailable(Student $student): void
    {
        if ($student->enquiries()->exists()) {
            throw ValidationException::withMessages([
                'mobile' => 'This mobile number already has an enquiry on file. Open the student profile to add a visit or continue admission.',
            ]);
        }

        $student->enquiries()->onlyTrashed()->each(function (Enquiry $enquiry): void {
            Visit::query()
                ->where('enquiry_id', $enquiry->id)
                ->update(['enquiry_id' => null]);

            $enquiry->forceDelete();
        });
    }

    public static function ensureAdmissionSlotAvailable(Student $student): void
    {
        if (Admission::query()->where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages([
                'admission' => 'This student already has an admission on file.',
            ]);
        }

        Admission::onlyTrashed()
            ->where('student_id', $student->id)
            ->whereDoesntHave('enrollment')
            ->each(fn (Admission $admission) => $admission->forceDelete());

        if (Admission::withTrashed()->where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages([
                'admission' => 'A previously deleted admission is still on file. Permanently remove it before starting a new admission.',
            ]);
        }
    }

    public static function ensureEnquiryAdmissionSlotAvailable(Enquiry $enquiry): void
    {
        if ($enquiry->admission()->exists()) {
            throw ValidationException::withMessages([
                'enquiry_id' => 'An admission already exists for this enquiry.',
            ]);
        }

        $enquiry->admission()->onlyTrashed()->whereDoesntHave('enrollment')->each(
            fn (Admission $admission) => $admission->forceDelete(),
        );

        if ($enquiry->admission()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'enquiry_id' => 'A previously deleted admission is linked to this enquiry. Permanently remove it before converting again.',
            ]);
        }
    }
}
