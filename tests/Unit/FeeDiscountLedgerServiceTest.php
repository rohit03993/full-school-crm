<?php

namespace Tests\Unit;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\FeeDiscountEntry;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeDiscountLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeDiscountLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_each_discount_change_as_separate_entry(): void
    {
        $staff = User::factory()->create();

        $student = Student::query()->create([
            'name' => 'Discount Test',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543210',
            'status' => StudentStatus::AdmissionSubmitted,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 12',
            'code' => 'CLS-12',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 120000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000010',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-2026-000010',
            'course_fee' => 120000,
            'discount_amount' => 5000,
            'net_fee' => 115000,
            'status' => AdmissionStatus::Submitted,
        ]);

        $ledger = app(FeeDiscountLedgerService::class);

        $ledger->recordAdmissionChange($admission, 5000, 8000, $staff, 'Sibling concession');
        $ledger->recordAdmissionChange($admission, 8000, 10000, $staff, 'Festival offer');

        $entries = FeeDiscountEntry::query()->where('admission_id', $admission->id)->get();

        $this->assertCount(2, $entries);
        $this->assertSame('3000.00', $entries[0]->amount);
        $this->assertSame('8000.00', $entries[0]->total_after);
        $this->assertSame('2000.00', $entries[1]->amount);
        $this->assertSame('10000.00', $entries[1]->total_after);
    }
}
