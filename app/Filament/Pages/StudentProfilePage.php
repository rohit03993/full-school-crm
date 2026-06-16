<?php

namespace App\Filament\Pages;

use App\Enums\ActivityKind;
use App\Enums\BatchStatus;
use App\Enums\LeadSource;
use App\Models\ActivityAttendance;
use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\Batch;
use App\Filament\Forms\AddPaymentFormSchema;
use App\Filament\Forms\ConvertToAdmissionFormSchema;
use App\Filament\Forms\EnquiryFormSchema;
use App\Filament\Forms\StudentProfileFormSchema;
use App\Models\Admission;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Visit;
use App\Services\ActivityAttendanceService;
use App\Services\AdmissionService;
use App\Services\AttendanceService;
use App\Services\BatchService;
use App\Services\ConvertToAdmissionPresenter;
use App\Services\EnquiryService;
use App\Services\FeeStructureService;
use App\Services\IdCardService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use App\Services\StorageCleanupService;
use App\Services\StudentCounterService;
use App\Services\StudentUpdateService;
use App\Services\VisitService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class StudentProfilePage extends Page
{
    use WithFileUploads;

    protected static ?string $slug = 'students/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public Student $record;

    public string $profileTab = 'overview';

    public bool $visitsTabLoaded = false;

    public bool $admissionTabLoaded = false;

    public bool $documentsTabLoaded = false;

    public bool $feesTabLoaded = false;

    public bool $receiptsTabLoaded = false;

    public bool $attendanceTabLoaded = false;

    public bool $practicalsTabLoaded = false;

    public bool $industrialVisitsTabLoaded = false;

    public bool $seminarsTabLoaded = false;

    public bool $showIdCardPreview = false;

    public ?Batch $activeBatch = null;

  /**
     * @var Collection<int, Attendance>
     */
    public Collection $attendanceRecords;

    public ?float $attendancePercentage = null;

  /**
     * @var Collection<int, ActivityAttendance>
     */
    public Collection $practicalRecords;

  /**
     * @var Collection<int, ActivityAttendance>
     */
    public Collection $industrialVisitRecords;

  /**
     * @var Collection<int, ActivityAttendance>
     */
    public Collection $seminarRecords;

  /**
     * @var Collection<int, Visit>
     */
    public Collection $visits;

  /**
     * @var Collection<int, Document>
     */
    public Collection $documents;

  /**
     * @var Collection<int, Payment>
     */
    public Collection $payments;

    public ?Admission $activeAdmission = null;

    public ?string $tenthBoard = null;

    public ?string $tenthPercentage = null;

    public ?string $twelfthBoard = null;

    public ?string $twelfthPercentage = null;

    public ?string $graduation = null;

    public ?string $graduationPercentage = null;

    public ?string $discountAmount = null;

    public ?TemporaryUploadedFile $uploadPhoto = null;

    public ?TemporaryUploadedFile $uploadAadhaar = null;

    public ?TemporaryUploadedFile $uploadMarksheet = null;

    public ?TemporaryUploadedFile $uploadSignature = null;

    public string $returnRemarks = '';

    public function mount(Student $record): void
    {
        $this->record = $record->load([
            'enquiries.course',
            'enquiries.meetingWith',
            'activeEnrollment.course',
            'activeEnrollment.feeStructure',
            'activeEnrollment.admission.documents',
        ]);

        $this->visits = new Collection;
        $this->documents = new Collection;
        $this->payments = new Collection;
        $this->attendanceRecords = new Collection;
        $this->practicalRecords = new Collection;
        $this->industrialVisitRecords = new Collection;
        $this->seminarRecords = new Collection;

        $tab = request()->query('tab');

        if (is_string($tab) && in_array($tab, [
            'overview', 'visits', 'admission', 'documents', 'fees', 'receipts', 'attendance',
            'practicals', 'industrial_visits', 'seminars',
        ], true)) {
            $this->profileTab = $tab;
            $this->updatedProfileTab();
        }
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/students/{record}';
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getHeading(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return $this->record->mobile;
    }

    public function updatedProfileTab(): void
    {
        match ($this->profileTab) {
            'visits' => $this->loadVisitsTab(),
            'admission' => $this->loadAdmissionTab(),
            'documents' => $this->loadDocumentsTab(),
            'fees' => $this->loadFeesTab(),
            'receipts' => $this->loadReceiptsTab(),
            'attendance' => $this->loadAttendanceTab(),
            'practicals' => $this->loadPracticalsTab(),
            'industrial_visits' => $this->loadIndustrialVisitsTab(),
            'seminars' => $this->loadSeminarsTab(),
            default => null,
        };
    }

    public function loadVisitsTab(): void
    {
        if ($this->visitsTabLoaded) {
            return;
        }

        $this->visitsTabLoaded = true;
        $this->visits = $this->record->visits()
            ->with(['staff', 'enquiry.course'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    public function loadAdmissionTab(): void
    {
        if ($this->admissionTabLoaded) {
            return;
        }

        $this->admissionTabLoaded = true;
        $this->activeAdmission = $this->record->admissions()
            ->with(['student', 'enquiry.course', 'documents', 'enrollment'])
            ->latest()
            ->first();

        if ($this->activeAdmission) {
            $this->tenthBoard = $this->activeAdmission->tenth_board;
            $this->tenthPercentage = $this->activeAdmission->tenth_percentage;
            $this->twelfthBoard = $this->activeAdmission->twelfth_board;
            $this->twelfthPercentage = $this->activeAdmission->twelfth_percentage;
            $this->graduation = $this->activeAdmission->graduation;
            $this->graduationPercentage = $this->activeAdmission->graduation_percentage;
            $this->discountAmount = (string) ($this->activeAdmission->discount_amount ?? 0);
        }
    }

    public function getAdmissionNetFeeProperty(): string
    {
        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));

        return number_format(max(0, $courseFee - $discount), 2);
    }

    public function saveAdmissionDiscount(AdmissionService $admissions): void
    {
        $this->loadAdmissionTab();

        if (! $this->activeAdmission?->canAdjustFees()) {
            return;
        }

        $this->validate([
            'discountAmount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->activeAdmission = $admissions->updateFees(
                $this->activeAdmission,
                (float) $this->discountAmount,
                Auth::user(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        Notification::make()
            ->title('Discount saved')
            ->body('Net fee is now ₹'.$this->admissionNetFee.'.')
            ->success()
            ->send();
    }

    public function loadDocumentsTab(): void
    {
        if ($this->documentsTabLoaded) {
            return;
        }

        $this->documentsTabLoaded = true;

        $admissionIds = $this->record->admissions()->pluck('id');

        $this->documents = Document::query()
            ->where('documentable_type', Admission::class)
            ->whereIn('documentable_id', $admissionIds)
            ->latest()
            ->get();
    }

    public function loadFeesTab(): void
    {
        if ($this->feesTabLoaded) {
            return;
        }

        $this->feesTabLoaded = true;
        $this->record->loadMissing(['activeEnrollment.course', 'activeEnrollment.feeStructure']);
        $this->loadPayments();
    }

    public function loadReceiptsTab(): void
    {
        if ($this->receiptsTabLoaded) {
            return;
        }

        $this->receiptsTabLoaded = true;
        $this->loadPayments();
    }

    protected function loadPayments(): void
    {
        $this->payments = Payment::query()
            ->where('student_id', $this->record->id)
            ->with(['addedBy.staffProfile', 'feeStructure.enrollment.course'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    public function loadAttendanceTab(): void
    {
        if ($this->attendanceTabLoaded) {
            return;
        }

        $this->attendanceTabLoaded = true;
        $this->record->loadMissing(['activeBatchStudent.batch.trainer']);

        $this->activeBatch = $this->record->activeBatchStudent?->batch;

        if (! $this->activeBatch) {
            $this->attendanceRecords = new Collection;
            $this->attendancePercentage = null;

            return;
        }

        $this->attendanceRecords = Attendance::query()
            ->where('batch_id', $this->activeBatch->id)
            ->where('student_id', $this->record->id)
            ->orderByDesc('attendance_date')
            ->limit(100)
            ->get();

        $this->attendancePercentage = app(AttendanceService::class)->percentageForStudent($this->record);
    }

    public function loadPracticalsTab(): void
    {
        if ($this->practicalsTabLoaded) {
            return;
        }

        $this->practicalsTabLoaded = true;
        $this->practicalRecords = app(ActivityAttendanceService::class)
            ->presentRecordsForStudent($this->record, ActivityKind::Practical);
    }

    public function loadIndustrialVisitsTab(): void
    {
        if ($this->industrialVisitsTabLoaded) {
            return;
        }

        $this->industrialVisitsTabLoaded = true;
        $this->industrialVisitRecords = app(ActivityAttendanceService::class)
            ->presentRecordsForStudent($this->record, ActivityKind::IndustrialVisit);
    }

    public function loadSeminarsTab(): void
    {
        if ($this->seminarsTabLoaded) {
            return;
        }

        $this->seminarsTabLoaded = true;
        $this->seminarRecords = app(ActivityAttendanceService::class)
            ->presentRecordsForStudent($this->record, ActivityKind::Seminar);
    }

    public function openIdCardPreview(): void
    {
        if (! $this->record->activeEnrollment?->hasIdCard()) {
            return;
        }

        $this->showIdCardPreview = true;
    }

    public function closeIdCardPreview(): void
    {
        $this->showIdCardPreview = false;
    }

    public function generateIdCard(IdCardService $idCards): void
    {
        $enrollment = $this->record->activeEnrollment;

        if (! $enrollment?->canGenerateIdCard()) {
            Notification::make()
                ->title('ID card unavailable')
                ->body('At least one payment is required before generating an ID card.')
                ->warning()
                ->send();

            return;
        }

        $idCards->generateForEnrollment($enrollment, Auth::user());
        $this->refreshRecord();

        Notification::make()
            ->title('ID card generated')
            ->body("PDF ready for {$enrollment->enrollment_number}.")
            ->success()
            ->send();
    }

    public function regenerateIdCard(IdCardService $idCards): void
    {
        abort_unless(Auth::user()?->hasRole(RoleName::SuperAdmin->value), 403);

        $enrollment = $this->record->activeEnrollment;

        if (! $enrollment) {
            return;
        }

        $idCards->generateForEnrollment($enrollment, Auth::user(), regenerate: true);
        $this->refreshRecord();

        Notification::make()
            ->title('ID card regenerated')
            ->body('Latest student photo and details applied.')
            ->success()
            ->send();
    }

    public function generateReceipt(int $paymentId, ReceiptService $receipts): void
    {
        $payment = Payment::query()
            ->where('student_id', $this->record->id)
            ->findOrFail($paymentId);

        if ($payment->hasReceiptPdf()) {
            return;
        }

        $receipts->generateForPayment($payment, Auth::user());

        $this->feesTabLoaded = false;
        $this->receiptsTabLoaded = false;
        $this->updatedProfileTab();

        Notification::make()
            ->title('Receipt generated')
            ->body("PDF ready for {$payment->receipt_number}.")
            ->success()
            ->send();
    }

    public function regenerateReceipt(int $paymentId, ReceiptService $receipts): void
    {
        abort_unless(Auth::user()?->hasRole(RoleName::SuperAdmin->value), 403);

        $payment = Payment::query()
            ->where('student_id', $this->record->id)
            ->findOrFail($paymentId);

        $receipts->generateForPayment($payment, Auth::user(), regenerate: true);

        $this->feesTabLoaded = false;
        $this->receiptsTabLoaded = false;
        $this->updatedProfileTab();

        Notification::make()
            ->title('Receipt regenerated')
            ->body("Previous PDF removed. Latest copy ready for {$payment->receipt_number}.")
            ->success()
            ->send();
    }

    public function submitAdmissionForm(AdmissionService $admissions): void
    {
        $this->loadAdmissionTab();

        if (! $this->activeAdmission) {
            return;
        }

        $this->validate([
            'discountAmount' => ['nullable', 'numeric', 'min:0'],
            'uploadPhoto' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'uploadAadhaar' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'uploadMarksheet' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'uploadSignature' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        try {
            $admissions->submitForm(
                $this->activeAdmission,
                [
                    'tenth_board' => $this->tenthBoard,
                    'tenth_percentage' => $this->tenthPercentage,
                    'twelfth_board' => $this->twelfthBoard,
                    'twelfth_percentage' => $this->twelfthPercentage,
                    'graduation' => $this->graduation,
                    'graduation_percentage' => $this->graduationPercentage,
                ],
                array_filter([
                    'photo' => $this->uploadPhoto,
                    'aadhaar' => $this->uploadAadhaar,
                    'marksheet' => $this->uploadMarksheet,
                    'signature' => $this->uploadSignature,
                ]),
                Auth::user(),
                $this->activeAdmission->canAdjustFees() ? (float) ($this->discountAmount ?? 0) : null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->resetAdmissionUploads();
        app(StorageCleanupService::class)->pruneLivewireTempFiles(0);

        $this->refreshRecord();
        $this->admissionTabLoaded = false;
        $this->documentsTabLoaded = false;
        $this->loadAdmissionTab();

        Notification::make()
            ->title('Admission form submitted')
            ->body('Status updated to verification pending.')
            ->success()
            ->send();
    }

    protected function resetAdmissionUploads(): void
    {
        $this->uploadPhoto = null;
        $this->uploadAadhaar = null;
        $this->uploadMarksheet = null;
        $this->uploadSignature = null;
    }

    public function approveAdmission(AdmissionService $admissions): void
    {
        $this->loadAdmissionTab();

        if (! $this->activeAdmission) {
            return;
        }

        $enrollment = $admissions->approve($this->activeAdmission, Auth::user());

        $this->refreshRecord();
        $this->admissionTabLoaded = false;
        $this->feesTabLoaded = false;
        $this->loadAdmissionTab();
        $this->profileTab = 'fees';
        $this->loadFeesTab();

        Notification::make()
            ->title('Admission approved')
            ->body("Enrollment {$enrollment->enrollment_number} created. Set up fees in the Fees tab.")
            ->success()
            ->send();
    }

    public function returnAdmission(AdmissionService $admissions): void
    {
        $this->validate([
            'returnRemarks' => ['required', 'string', 'max:1000'],
        ]);

        $this->loadAdmissionTab();

        if (! $this->activeAdmission) {
            return;
        }

        $admissions->returnForCorrection($this->activeAdmission, Auth::user(), $this->returnRemarks);

        $this->returnRemarks = '';
        $this->refreshRecord();
        $this->admissionTabLoaded = false;
        $this->loadAdmissionTab();

        Notification::make()
            ->title('Admission returned')
            ->body('Student can correct and resubmit the form.')
            ->warning()
            ->send();
    }

    protected function refreshRecord(): void
    {
        $this->record->refresh()->load([
            'enquiries.course',
            'activeEnrollment.course',
            'activeEnrollment.feeStructure',
            'activeEnrollment.admission.documents',
            'activeBatchStudent.batch.trainer',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSearch')
                ->label('Back to Search')
                ->url(StudentSearchPage::getUrl())
                ->color('gray'),
            Action::make('editStudent')
                ->label('Edit Details')
                ->icon('heroicon-o-pencil-square')
                ->form(StudentProfileFormSchema::forEdit())
                ->fillForm(fn (): array => [
                    'name' => $this->record->name,
                    'father_name' => $this->record->father_name,
                    'date_of_birth' => $this->record->date_of_birth,
                    'gender' => $this->record->gender?->value,
                    'mobile' => $this->record->mobile,
                    'alternate_mobile' => $this->record->alternate_mobile,
                    'email' => $this->record->email,
                    'address' => $this->record->address,
                    'city' => $this->record->city,
                    'state' => $this->record->state,
                    'pincode' => $this->record->pincode,
                    'category' => $this->record->category?->value,
                ])
                ->action(function (array $data, StudentUpdateService $studentUpdates): void {
                    $this->record = $studentUpdates->update($this->record, $data, Auth::user());

                    Notification::make()
                        ->title('Student updated')
                        ->body('Profile details saved successfully.')
                        ->success()
                        ->send();
                }),
            Action::make('convertToAdmission')
                ->label('Convert to Admission')
                ->icon('heroicon-o-arrow-right-circle')
                ->modalHeading('Convert to Admission')
                ->modalDescription(function (ConvertToAdmissionPresenter $presenter): string {
                    return $presenter->modalDescription(
                        $presenter->convertibleEnquiries($this->record),
                    );
                })
                ->fillForm(function (ConvertToAdmissionPresenter $presenter): array {
                    $convertible = $presenter->convertibleEnquiries($this->record);

                    return ConvertToAdmissionFormSchema::initialState($convertible, $presenter);
                })
                ->form(function (ConvertToAdmissionPresenter $presenter): array {
                    $convertible = $presenter->convertibleEnquiries($this->record);

                    if ($convertible->isEmpty()) {
                        return [];
                    }

                    return ConvertToAdmissionFormSchema::fields($convertible, $presenter);
                })
                ->action(function (array $data, ConvertToAdmissionPresenter $presenter, AdmissionService $admissions): void {
                    $convertible = $presenter->convertibleEnquiries($this->record);

                    if ($convertible->isEmpty()) {
                        return;
                    }

                    $enquiryId = $data['enquiry_id'] ?? $presenter->defaultEnquiryId($convertible);
                    $enquiry = Enquiry::query()->findOrFail($enquiryId);

                    $admission = $admissions->convert(
                        $this->record,
                        $enquiry,
                        Auth::user(),
                        [
                            'course_id' => (int) $data['course_id'],
                            'discount_amount' => $data['discount_amount'] ?? 0,
                        ],
                    );

                    $this->refreshRecord();
                    $this->profileTab = 'admission';
                    $this->admissionTabLoaded = false;
                    $this->loadAdmissionTab();

                    Notification::make()
                        ->title('Converted to admission')
                        ->body("Admission {$admission->admission_number} for {$admission->enquiry?->course?->name} · Net fee ₹".number_format((float) $admission->net_fee, 2))
                        ->success()
                        ->send();
                })
                ->visible(fn (ConvertToAdmissionPresenter $presenter): bool => $presenter
                    ->convertibleEnquiries($this->record)
                    ->isNotEmpty()),
            Action::make('assignBatch')
                ->label('Assign Batch')
                ->icon('heroicon-o-user-group')
                ->form([
                    Select::make('batch_id')
                        ->label('Batch')
                        ->options(function (): array {
                            $courseId = $this->record->activeEnrollment?->course_id;

                            if (! $courseId) {
                                return [];
                            }

                            return Batch::query()
                                ->where('status', BatchStatus::Active)
                                ->where('course_id', $courseId)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->default(fn (): ?int => $this->record->activeBatchStudent?->batch_id)
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->helperText('Only one active batch per student. Assigning a new batch deactivates the previous one.'),
                ])
                ->action(function (array $data, BatchService $batches): void {
                    $batch = Batch::query()->findOrFail($data['batch_id']);
                    $batches->assign($this->record, $batch, Auth::user());

                    $this->refreshRecord();
                    $this->attendanceTabLoaded = false;
                    $this->loadAttendanceTab();

                    Notification::make()
                        ->title('Batch assigned')
                        ->body("{$this->record->name} is now in {$batch->name}.")
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->activeEnrollment !== null),
            Action::make('adjustFees')
                ->label('Adjust Fees')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->modalHeading('Adjust fee structure')
                ->modalDescription('Admin only. Changes are recorded in fee history and audit log.')
                ->fillForm(fn (): array => [
                    'course_fee' => $this->record->activeEnrollment?->feeStructure?->course_fee,
                    'discount_amount' => $this->record->activeEnrollment?->feeStructure?->discount_amount,
                ])
                ->form([
                    TextInput::make('course_fee')
                        ->label('Course fee')
                        ->numeric()
                        ->prefix('₹')
                        ->required()
                        ->minValue(0)
                        ->step(0.01),
                    TextInput::make('discount_amount')
                        ->label('Discount')
                        ->numeric()
                        ->prefix('₹')
                        ->required()
                        ->minValue(0)
                        ->step(0.01),
                    Textarea::make('reason')
                        ->label('Reason for change')
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data, FeeStructureService $fees): void {
                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return;
                    }

                    $fees->updateByAdmin($feeStructure, $data, Auth::user());
                    $this->refreshRecord();
                    $this->feesTabLoaded = false;
                    $this->loadFeesTab();

                    Notification::make()
                        ->title('Fees updated')
                        ->body('Fee structure revised and history recorded.')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => Auth::user()?->hasRole(RoleName::SuperAdmin->value)
                    && $this->record->activeEnrollment?->feeStructure !== null),
            Action::make('addPayment')
                ->label('Add Payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->form(function (): array {
                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return [];
                    }

                    return AddPaymentFormSchema::fields($feeStructure);
                })
                ->action(function (array $data, PaymentService $payments): void {
                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return;
                    }

                    $proof = $data['proof_image'] ?? null;

                    if (is_array($proof)) {
                        $proof = $proof[0] ?? null;
                    }

                    $payment = $payments->add(
                        $feeStructure,
                        $this->record,
                        $data,
                        (string) $proof,
                        Auth::user(),
                    );

                    app(StorageCleanupService::class)->pruneLivewireTempFiles(0);

                    $this->refreshRecord();
                    $this->feesTabLoaded = false;
                    $this->receiptsTabLoaded = false;
                    $this->profileTab = 'fees';
                    $this->loadFeesTab();
                    $this->refreshRecord();

                    $body = "Receipt {$payment->receipt_number} · ₹".number_format((float) $payment->amount, 2).' · PDF generated';

                    if (Payment::query()->where('fee_structure_id', $feeStructure->id)->count() === 1) {
                        $body .= ' · ID card generated';
                    }

                    Notification::make()
                        ->title('Payment recorded')
                        ->body($body)
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->activeEnrollment?->feeStructure !== null
                    && (float) ($this->record->activeEnrollment->feeStructure->pending_amount ?? 0) > 0),
            Action::make('addVisit')
                ->label('Add Visit')
                ->icon('heroicon-o-plus')
                ->form(EnquiryFormSchema::visitActionFields(
                    Auth::id(),
                    $this->record->enquiries,
                ))
                ->action(function (array $data) {
                    $enquiry = Enquiry::query()->findOrFail($data['enquiry_id']);

                    app(VisitService::class)->add(
                        $this->record,
                        $enquiry,
                        [
                            'visit_date' => $data['visit_date'],
                            'discussion_summary' => $data['discussion_summary'],
                            'remarks' => $data['remarks'] ?? null,
                            'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
                            'status' => $data['status'],
                        ],
                        Auth::user(),
                    );

                    $this->visitsTabLoaded = false;
                    $this->loadVisitsTab();
                    $this->refreshRecord();

                    Notification::make()
                        ->title('Visit added')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->enquiries->isNotEmpty()),
            Action::make('addEnquiry')
                ->label('Add New Enquiry')
                ->icon('heroicon-o-document-plus')
                ->form(EnquiryFormSchema::forExistingStudent(Auth::id()))
                ->action(function (array $data) {
                    $data['meeting_with_user_id'] = Auth::id();

                    app(EnquiryService::class)->createForExistingStudent(
                        $this->record,
                        $data,
                        Auth::user(),
                        LeadSource::WalkIn,
                    );

                    $this->refreshRecord();

                    Notification::make()
                        ->title('Enquiry added')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->enquiries()->doesntExist()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.student-id-card-preview-modal')
                ->viewData(fn (): array => [
                    'record' => $this->record,
                    'showIdCardPreview' => $this->showIdCardPreview,
                ]),
            View::make('filament.pages.partials.student-profile-header')
                ->viewData(fn (): array => [
                    'record' => $this->record,
                    'profile' => app(StudentCounterService::class)->profile($this->record),
                ]),
            Tabs::make('Student Profile')
                ->livewireProperty('profileTab')
                ->tabs([
                    'overview' => Tab::make('Overview')
                        ->schema([
                            View::make('filament.pages.partials.student-profile-overview')
                                ->viewData(fn (): array => [
                                    'record' => $this->record,
                                    'enquiries' => $this->record->enquiries,
                                    'profile' => app(StudentCounterService::class)->profile($this->record),
                                ]),
                        ]),
                    'visits' => Tab::make('Visits')
                        ->schema([
                            View::make('filament.pages.partials.student-profile-visits')
                                ->viewData(fn (): array => [
                                    'visitsTabLoaded' => $this->visitsTabLoaded,
                                    'visits' => $this->visits,
                                ]),
                        ]),
                    'admission' => Tab::make('Admission')
                        ->schema([
                            View::make('filament.pages.partials.student-profile-admission')
                                ->viewData(fn (): array => [
                                    'admissionTabLoaded' => $this->admissionTabLoaded,
                                    'activeAdmission' => $this->activeAdmission,
                                    'record' => $this->record,
                                ]),
                        ]),
                    'documents' => Tab::make('Documents')
                        ->schema([
                            View::make('filament.pages.partials.student-profile-documents')
                                ->viewData(fn (): array => [
                                    'documentsTabLoaded' => $this->documentsTabLoaded,
                                    'documents' => $this->documents,
                                ]),
                        ]),
                    'fees' => Tab::make('Fees')
                        ->visible(fn (): bool => $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-fees')
                                ->viewData(fn (): array => [
                                    'record' => $this->record->loadMissing(['activeEnrollment.course', 'activeEnrollment.feeStructure']),
                                    'feesTabLoaded' => $this->feesTabLoaded,
                                    'payments' => $this->payments,
                                ]),
                        ]),
                    'receipts' => Tab::make('Receipts')
                        ->visible(fn (): bool => $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-receipts')
                                ->viewData(fn (): array => [
                                    'receiptsTabLoaded' => $this->receiptsTabLoaded,
                                    'payments' => $this->payments,
                                    'isSuperAdmin' => Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false,
                                ]),
                        ]),
                    'attendance' => Tab::make('Attendance')
                        ->visible(fn (): bool => $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-attendance')
                                ->viewData(fn (): array => [
                                    'attendanceTabLoaded' => $this->attendanceTabLoaded,
                                    'activeBatch' => $this->activeBatch,
                                    'attendanceRecords' => $this->attendanceRecords,
                                    'attendancePercentage' => $this->attendancePercentage,
                                ]),
                        ]),
                    'practicals' => Tab::make('Practicals')
                        ->visible(fn (): bool => $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-practicals')
                                ->viewData(fn (): array => [
                                    'practicalsTabLoaded' => $this->practicalsTabLoaded,
                                    'practicalRecords' => $this->practicalRecords,
                                ]),
                        ]),
                    'industrial_visits' => Tab::make('Industrial Visits')
                        ->visible(fn (): bool => $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-industrial-visits')
                                ->viewData(fn (): array => [
                                    'industrialVisitsTabLoaded' => $this->industrialVisitsTabLoaded,
                                    'industrialVisitRecords' => $this->industrialVisitRecords,
                                ]),
                        ]),
                    'seminars' => Tab::make('Seminars')
                        ->visible(fn (): bool => $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-seminars')
                                ->viewData(fn (): array => [
                                    'seminarsTabLoaded' => $this->seminarsTabLoaded,
                                    'seminarRecords' => $this->seminarRecords,
                                ]),
                        ]),
                ]),
        ]);
    }
}
