<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentPortal\Concerns\ResolvesPortalStudent;
use App\Enums\ResultDeclarationStatus;
use App\Models\ActivityType;
use App\Models\Admission;
use App\Models\Payment;
use App\Models\StudentMarksheet;
use App\Services\ActivityAttendanceService;
use App\Services\AdmissionService;
use App\Services\AttendanceService;
use App\Support\PublishedResultsGate;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesPortalStudent;

    public function index(): View
    {
        $student = $this->portalStudent();

        $admission = $student->admissions()
            ->with(['enquiry.course', 'documents', 'enrollment'])
            ->latest()
            ->first();

        $enrollment = $student->activeEnrollment;
        $fees = $enrollment?->feeStructure;

        $enrollment?->feeStructure?->loadMissing(['installments', 'miscCharges']);

        $payments = $fees
            ? Payment::query()
                ->where('student_id', $student->id)
                ->where('fee_structure_id', $fees->id)
                ->with('feeInstallment')
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->get()
            : collect();

        $examMarksSections = [];
        $publishedResults = [];
        $sessionAttendanceRecords = collect();
        $classAttendancePercentage = null;

        if ($enrollment) {
            $attendance = app(ActivityAttendanceService::class);
            $sessionAttendanceRecords = $attendance->sessionAttendanceRecordsForStudent($student);
            $classAttendancePercentage = app(AttendanceService::class)->percentageForStudent($student);

            foreach (ActivityType::query()->enabled()->ordered()->get() as $activityType) {
                if (! $activityType->supportsScoring()) {
                    continue;
                }

                $records = PublishedResultsGate::filterRecordsForPortal(
                    $attendance->presentRecordsForStudent($student, $activityType),
                );

                if ($records->isEmpty()) {
                    continue;
                }

                $examMarksSections[] = [
                    'label' => $activityType->name,
                    'matrix' => StudentExamMarksMatrix::fromRecords($records),
                ];
            }

            $publishedResults = StudentMarksheet::query()
                ->where('student_id', $student->id)
                ->whereHas('resultDeclaration', fn ($query) => $query
                    ->whereNotNull('declared_at')
                    ->where('status', ResultDeclarationStatus::Published))
                ->with('resultDeclaration')
                ->latest('id')
                ->get();
        }

        return view('portal.dashboard', [
            'student' => $student,
            'admission' => $admission,
            'canFillForm' => $admission?->isEditable() ?? false,
            'enrollment' => $enrollment,
            'fees' => $fees,
            'installments' => $fees?->installments ?? collect(),
            'miscCharges' => $fees?->miscCharges ?? collect(),
            'payments' => $payments,
            'examMarksSections' => $examMarksSections,
            'publishedResults' => $publishedResults,
            'sessionAttendanceRecords' => $sessionAttendanceRecords,
            'classAttendancePercentage' => $classAttendancePercentage,
        ]);
    }

    public function submitAdmission(Request $request, AdmissionService $admissions): RedirectResponse
    {
        $student = $this->portalStudent();

        $admission = $student->admissions()
            ->with('documents')
            ->latest()
            ->first();

        if (! $admission || ! $admission->isEditable()) {
            return back()->withErrors(['admission' => 'No editable admission form is available.']);
        }

        $validated = $request->validate([
            'tenth_board' => ['nullable', 'string', 'max:100'],
            'tenth_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'twelfth_board' => ['nullable', 'string', 'max:100'],
            'twelfth_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'graduation' => ['nullable', 'string', 'max:100'],
            'graduation_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'photo' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'aadhaar' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'marksheet' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'signature' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        try {
            $admissions->submitForm(
                $admission,
                $validated,
                array_filter([
                    'photo' => $request->file('photo'),
                    'aadhaar' => $request->file('aadhaar'),
                    'marksheet' => $request->file('marksheet'),
                    'signature' => $request->file('signature'),
                ]),
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('portal_success', 'Admission form submitted successfully. Our team will verify your documents.');
    }
}
