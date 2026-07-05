<?php

namespace App\Filament\Pages;

use App\Enums\CallStatus;
use App\Enums\BatchStatus;
use App\Enums\LeadSource;
use App\Models\ActivityAttendance;
use App\Models\ActivityType;
use App\Enums\CrmPermission;
use App\Enums\DocumentType;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Support\CrmPagination;
use App\Models\Attendance;
use App\Models\Batch;
use App\Filament\Concerns\HandlesLogCallModal;
use App\Filament\Forms\AdjustFeeStructureFormSchema;
use App\Filament\Forms\AddPaymentFormSchema;
use App\Filament\Forms\ConvertToAdmissionFormSchema;
use App\Filament\Forms\EnquiryFormSchema;
use App\Filament\Forms\StudentProfileFormSchema;
use App\Models\Admission;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\FeePenalty;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityAttendanceService;
use App\Services\AdmissionFeePlanService;
use App\Services\AdmissionService;
use App\Services\AttendanceService;
use App\Services\BatchService;
use App\Services\CallLogService;
use App\Services\ConvertToAdmissionPresenter;
use App\Services\DocumentService;
use App\Services\EnquiryService;
use App\Services\EnrollmentRollNumberService;
use App\Services\FeeInstallmentService;
use App\Services\FeeStructureService;
use App\Services\HomeworkAssignmentService;
use App\Services\IdCardService;
use App\Services\LeadAssignmentService;
use App\Services\PaymentService;
use App\Services\PenaltyCalculationService;
use App\Services\ReceiptService;
use App\Services\StorageCleanupService;
use App\Services\StudentCounterService;
use App\Services\StudentUpdateService;
use App\Services\WhatsAppCampaignService;
use App\Services\VisitService;
use App\Support\FeePlanCalculator;
use App\Support\FeePlanSubmissionGuard;
use App\Support\CrmHint;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class StudentProfilePage extends Page
{
    use HandlesLogCallModal;
    use WithFileUploads;

    protected static ?string $slug = 'students/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::StudentsView);
    }

    public Student $record;

    public string $profileTab = 'overview';

    public ?string $activitySubTab = null;

    public bool $visitsTabLoaded = false;

    public bool $callsTabLoaded = false;

    public bool $messagesTabLoaded = false;

    public bool $admissionTabLoaded = false;

    public bool $documentsTabLoaded = false;

    public bool $feesTabLoaded = false;

    public bool $receiptsTabLoaded = false;

    public bool $attendanceTabLoaded = false;

    public bool $homeworkTabLoaded = false;

    /**
     * @var array<int, bool>
     */
    public array $activityTabLoaded = [];

    public bool $showIdCardPreview = false;

    public ?Batch $activeBatch = null;

  /**
     * @var Collection<int, Attendance>
     */
    public Collection $attendanceRecords;

    public ?float $attendancePercentage = null;

    /**
     * @var array<int, Collection<int, ActivityAttendance>>
     */
    public array $activityRecords = [];

  /**
     * @var Collection<int, Visit>
     */
    public Collection $visits;

    /**
     * @var Collection<int, StudentCall>
     */
    public Collection $calls;

    /**
     * @var Collection<int, \App\Models\WhatsAppCampaignRecipient>
     */
    public Collection $whatsappMessages;

    /**
     * @var Collection<int, \App\Models\HomeworkAssignment>
     */
    public Collection $homeworkAssignments;

    public ?int $sendWhatsAppTemplateId = null;

  /**
     * @var Collection<int, Document>
     */
    public Collection $documents;

    /**
     * @var Collection<int, Payment>
     */
    public Collection $payments;

    /**
     * @var Collection<int, \App\Models\FeeInstallment>
     */
    public Collection $installments;

    /**
     * @var Collection<int, \App\Models\FeeStructureHistory>
     */
    public Collection $feeStructureHistory;

    public ?Admission $activeAdmission = null;

    public ?string $tenthBoard = null;

    public ?string $tenthPercentage = null;

    public ?string $twelfthBoard = null;

    public ?string $twelfthPercentage = null;

    public ?string $graduation = null;

    public ?string $graduationPercentage = null;

    public ?string $discountAmount = null;

    public bool $useInstallmentPlan = false;

    /** @var array<int, array{label: string, amount: string}> */
    public array $miscFees = [];

    /** @var array<int, array{label: string, amount: string, due_date: ?string}> */
    public array $installmentPlan = [];

    /**
     * @var Collection<int, \App\Models\FeePenalty>
     */
    public Collection $penalties;

    public ?TemporaryUploadedFile $uploadPhoto = null;

    public ?TemporaryUploadedFile $uploadAadhaar = null;

    public ?TemporaryUploadedFile $uploadMarksheet = null;

    public ?TemporaryUploadedFile $uploadSignature = null;

    public string $returnRemarks = '';

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $cachedProfileSummary = null;

    /**
     * @var Collection<int, ActivityType>|null
     */
    protected ?Collection $cachedEnabledActivityTypes = null;

    public function mount(Student $record): void
    {
        $this->record = $record->load([
            'enquiries.course',
            'enquiries.meetingWith',
            'activeEnrollment.course',
            'activeEnrollment.feeStructure',
            'activeBatchStudent.batch.trainer',
            'lastCall.staff',
        ])->loadCount([
            'calls as not_connected_attempts_count' => fn ($query) => $query->whereIn(
                'call_status',
                CallStatus::notConnectedValues(),
            ),
        ]);

        $this->visits = new Collection;
        $this->calls = new Collection;
        $this->whatsappMessages = new Collection;
        $this->homeworkAssignments = new Collection;
        $this->documents = new Collection;
        $this->payments = new Collection;
        $this->installments = new Collection;
        $this->feeStructureHistory = new Collection;
        $this->penalties = new Collection;
        $this->attendanceRecords = new Collection;
        $this->activityRecords = [];

        $tab = request()->query('tab');

        if (is_string($tab)) {
            if (str_starts_with($tab, 'activity_')) {
                $this->profileTab = 'activities';
                $this->activitySubTab = substr($tab, strlen('activity_'));
            } elseif (in_array($tab, $this->validProfileTabs(), true)) {
                $this->profileTab = $tab;
            }

            $this->updatedProfileTab();
        }

        if (! in_array($this->profileTab, $this->validProfileTabs(), true)) {
            $this->profileTab = 'overview';
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
        return CrmHint::text('students.profile');
    }

    public function updatedProfileTab(): void
    {
        if (str_starts_with($this->profileTab, 'activity_')) {
            $this->activitySubTab = substr($this->profileTab, strlen('activity_'));
            $this->profileTab = 'activities';
        }

        if ($this->profileTab === 'activities') {
            $this->ensureActivitySubTabSelected();

            return;
        }

        match ($this->profileTab) {
            'visits' => $this->loadVisitsTab(),
            'calls' => $this->loadCallsTab(),
            'messages' => $this->loadMessagesTab(),
            'admission' => $this->loadAdmissionTab(),
            'documents' => $this->loadDocumentsTab(),
            'fees' => $this->loadFeesTab(),
            'receipts' => $this->loadReceiptsTab(),
            'attendance' => $this->loadAttendanceTab(),
            'homework' => $this->loadHomeworkTab(),
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function validProfileTabs(): array
    {
        $tabs = ['overview', 'documents'];

        if ($this->licensed(LicenseFeature::Enquiries)) {
            $tabs[] = 'visits';
        }

        if ($this->licensed(LicenseFeature::Calls)) {
            $tabs[] = 'calls';
        }

        if ($this->licensed(LicenseFeature::WhatsApp)) {
            $tabs[] = 'messages';
        }

        if ($this->licensed(LicenseFeature::Admissions)) {
            $tabs[] = 'admission';
        }

        if ($this->licensed(LicenseFeature::Fees) && $this->record->activeEnrollment !== null) {
            $tabs[] = 'fees';
            $tabs[] = 'receipts';
        }

        if ($this->licensed(LicenseFeature::Attendance) && $this->record->activeEnrollment !== null) {
            $tabs[] = 'attendance';
        }

        if ($this->licensed(LicenseFeature::Homework) && $this->record->activeBatchStudent !== null) {
            $tabs[] = 'homework';
        }

        if ($this->licensed(LicenseFeature::Marks)
            && $this->record->activeEnrollment !== null
            && $this->enabledActivityTypes()->isNotEmpty()) {
            $tabs[] = 'activities';
        }

        return $tabs;
    }

    protected function licensed(LicenseFeature $feature): bool
    {
        return FeatureGate::enabled($feature);
    }

    public function selectActivitySubTab(string $slug): void
    {
        $this->activitySubTab = $slug;
        $this->ensureActivitySubTabSelected();
    }

    protected function ensureActivitySubTabSelected(): void
    {
        $types = $this->enabledActivityTypes();

        if ($types->isEmpty()) {
            return;
        }

        if (blank($this->activitySubTab) || $types->firstWhere('slug', $this->activitySubTab) === null) {
            $this->activitySubTab = $types->first()->slug;
        }

        $type = $types->firstWhere('slug', $this->activitySubTab);

        if ($type) {
            $this->loadActivityTab($type->id);
        }
    }

    /**
     * @return Collection<int, ActivityType>
     */
    protected function enabledActivityTypes(): Collection
    {
        return $this->cachedEnabledActivityTypes ??= ActivityType::query()->enabled()->ordered()->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function profileSummary(): array
    {
        if ($this->cachedProfileSummary === null) {
            $this->cachedProfileSummary = app(StudentCounterService::class)->profile($this->record);
            $this->cachedProfileSummary['calling_assignment'] = app(LeadAssignmentService::class)
                ->profileCallingAssignment($this->record, Auth::user());
        }

        return $this->cachedProfileSummary;
    }

    public function loadActivityTab(int $activityTypeId): void
    {
        if ($this->activityTabLoaded[$activityTypeId] ?? false) {
            return;
        }

        $this->activityTabLoaded[$activityTypeId] = true;

        $type = $this->enabledActivityTypes()->firstWhere('id', $activityTypeId);

        $service = app(ActivityAttendanceService::class);

        $this->activityRecords[$activityTypeId] = $type && ! $type->supportsScoring()
            ? $service->recordsForStudent($this->record, $activityTypeId)
            : $service->presentRecordsForStudent($this->record, $activityTypeId);
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
            ->limit(CrmPagination::PER_PAGE)
            ->get();
    }

    public function loadCallsTab(): void
    {
        if ($this->callsTabLoaded) {
            return;
        }

        $this->callsTabLoaded = true;
        $this->calls = $this->record->calls()
            ->with(['staff', 'enquiry.course'])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->limit(CrmPagination::PER_PAGE)
            ->get();
    }

    public function loadMessagesTab(): void
    {
        if ($this->messagesTabLoaded) {
            return;
        }

        $this->messagesTabLoaded = true;
        $this->whatsappMessages = $this->record->whatsappMessages()
            ->with(['campaign.template'])
            ->orderByDesc('created_at')
            ->limit(CrmPagination::PER_PAGE)
            ->get();
    }

    public function sendWhatsAppMessage(): void
    {
        if (! $this->licensed(LicenseFeature::WhatsApp)) {
            Notification::make()
                ->title('WhatsApp module is not enabled')
                ->warning()
                ->send();

            return;
        }

        if (! $this->sendWhatsAppTemplateId) {
            Notification::make()
                ->title('Choose a template')
                ->warning()
                ->send();

            return;
        }

        if (blank($this->record->mobile)) {
            Notification::make()
                ->title('No mobile number')
                ->body('Add a mobile number before sending WhatsApp.')
                ->warning()
                ->send();

            return;
        }

        $template = WhatsAppTemplate::query()
            ->whereKey($this->sendWhatsAppTemplateId)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            Notification::make()->title('Template not found')->danger()->send();

            return;
        }

        app(WhatsAppCampaignService::class)->sendSingle($this->record, $template, Auth::user());

        $this->messagesTabLoaded = false;
        $this->loadMessagesTab();
        $this->sendWhatsAppTemplateId = null;

        Notification::make()
            ->title('WhatsApp queued')
            ->body('Your message is being sent. It may take a moment to deliver.')
            ->success()
            ->send();
    }

    public function loadAdmissionTab(): void
    {
        if ($this->admissionTabLoaded) {
            return;
        }

        $this->admissionTabLoaded = true;
        $this->activeAdmission = $this->record->admissions()
            ->with(['student', 'enquiry.course', 'documents', 'enrollment', 'miscFees', 'installmentPlans', 'discountSetBy', 'discountEntries.grantedBy'])
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
            $this->useInstallmentPlan = (bool) $this->activeAdmission->use_installment_plan;
            $this->miscFees = $this->activeAdmission->miscFees->map(fn ($row): array => [
                'label' => $row->label,
                'amount' => (string) $row->amount,
            ])->values()->all();
            $this->installmentPlan = $this->activeAdmission->installmentPlans->map(fn ($row): array => [
                'label' => $row->label,
                'amount' => (string) $row->amount,
                'due_date' => $row->due_date?->toDateString(),
            ])->values()->all();
        }
    }

    public function addMiscFeeRow(): void
    {
        $this->miscFees[] = ['label' => '', 'amount' => ''];
    }

    public function removeMiscFeeRow(int $index): void
    {
        unset($this->miscFees[$index]);
        $this->miscFees = array_values($this->miscFees);
    }

    public function addInstallmentRow(): void
    {
        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($this->miscFees);
        $netFee = max(0, $courseFee - $discount + $miscTotal);

        $this->installmentPlan[] = FeePlanCalculator::newInstallmentRow(
            $this->installmentPlan,
            $netFee,
            count($this->installmentPlan),
        );
    }

    public function removeInstallmentRow(int $index): void
    {
        unset($this->installmentPlan[$index]);
        $this->installmentPlan = FeePlanCalculator::sortAndRenumberInstallmentPlan(
            array_values($this->installmentPlan),
        );
    }

    public function updatedInstallmentPlan(mixed $value, ?string $key = null): void
    {
        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($this->miscFees);
        $netFee = max(0, $courseFee - $discount + $miscTotal);

        if (is_string($key) && str_ends_with($key, '.due_date')) {
            $this->installmentPlan = FeePlanCalculator::sortAndRenumberInstallmentPlan($this->installmentPlan);

            return;
        }

        if (is_string($key) && str_ends_with($key, '.amount')) {
            $this->installmentPlan = FeePlanCalculator::autoFillSingleEmptyRow($this->installmentPlan, $netFee);
        }
    }

    public function suggestInstallmentPlan(): void
    {
        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($this->miscFees);
        $netFee = max(0, $courseFee - $discount + $miscTotal);
        $course = $this->activeAdmission?->enquiry?->course;

        if ($course) {
            $course->loadMissing('installmentTemplates');
            $templatePlan = FeePlanCalculator::planFromCourseTemplates($course, $netFee);

            if ($templatePlan !== []) {
                $this->installmentPlan = $templatePlan;
                $this->useInstallmentPlan = true;

                return;
            }
        }

        $this->installmentPlan = FeePlanCalculator::defaultTwoPartPlan($netFee);
        $this->useInstallmentPlan = true;
    }

    public function fillInstallmentBalance(): void
    {
        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($this->miscFees);
        $netFee = max(0, $courseFee - $discount + $miscTotal);

        if ($this->installmentPlan === []) {
            $this->installmentPlan = [FeePlanCalculator::singleFullFeeRow($netFee)];

            return;
        }

        $this->installmentPlan = FeePlanCalculator::fillBalanceOnLastRow($this->installmentPlan, $netFee);
    }

    public function updatedUseInstallmentPlan(bool $value): void
    {
        if (! $value) {
            return;
        }

        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($this->miscFees);
        $netFee = max(0, $courseFee - $discount + $miscTotal);

        if ($this->installmentPlan === [] && $netFee > 0) {
            $course = $this->activeAdmission?->enquiry?->course;

            if ($course) {
                $course->loadMissing('installmentTemplates');
                $templatePlan = FeePlanCalculator::planFromCourseTemplates($course, $netFee);

                if ($templatePlan !== []) {
                    $this->installmentPlan = $templatePlan;

                    return;
                }
            }

            $this->installmentPlan = [FeePlanCalculator::singleFullFeeRow($netFee)];
        }
    }

    public function getAdmissionInstallmentSummaryProperty(): string
    {
        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($this->miscFees);
        $netFee = max(0, $courseFee - $discount + $miscTotal);

        return FeePlanCalculator::formatSummary($netFee, $this->installmentPlan);
    }

    public function getAdmissionInstallmentWarningProperty(): ?string
    {
        if (! $this->useInstallmentPlan) {
            return null;
        }

        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($this->miscFees);
        $netFee = max(0, $courseFee - $discount + $miscTotal);

        return FeePlanCalculator::unallocatedWarningMessage($netFee, $this->installmentPlan);
    }

    public function getCanSaveAdmissionFeePlanProperty(): bool
    {
        if (! $this->useInstallmentPlan) {
            return true;
        }

        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($this->miscFees);
        $netFee = max(0, $courseFee - $discount + $miscTotal);

        if ($netFee <= 0) {
            return true;
        }

        return FeePlanCalculator::isFullyAllocated($netFee, $this->installmentPlan);
    }

    public function getAdmissionCourseFeeZeroProperty(): bool
    {
        return (float) ($this->activeAdmission?->course_fee ?? 0) <= 0;
    }

    public function getAdmissionNetFeeProperty(): string
    {
        $courseFee = (float) ($this->activeAdmission?->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));
        $miscTotal = round(collect($this->miscFees)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2);

        return number_format(max(0, $courseFee - $discount + $miscTotal), 2);
    }

    protected function userCan(CrmPermission $permission): bool
    {
        return CrmAccess::can(Auth::user(), $permission);
    }

    public function getCanManageAdmissionFeePlanProperty(): bool
    {
        return $this->activeAdmission?->canAdjustFees()
            && $this->userCan(CrmPermission::AdmissionsApprove);
    }

    public function saveAdmissionFeePlan(AdmissionService $admissions): void
    {
        abort_unless($this->canManageAdmissionFeePlan, 403);
        $this->loadAdmissionTab();

        if (! $this->activeAdmission?->canAdjustFees()) {
            return;
        }

        $this->validate([
            'discountAmount' => ['required', 'numeric', 'min:0'],
            'useInstallmentPlan' => ['boolean'],
            'miscFees' => ['array'],
            'miscFees.*.label' => ['nullable', 'string', 'max:100'],
            'miscFees.*.amount' => ['nullable', 'numeric', 'min:0'],
            'installmentPlan' => ['array'],
            'installmentPlan.*.label' => ['nullable', 'string', 'max:100'],
            'installmentPlan.*.amount' => ['nullable', 'numeric', 'min:0'],
            'installmentPlan.*.due_date' => ['nullable', 'date'],
        ]);

        try {
            $this->activeAdmission = $admissions->updateFeePlan($this->activeAdmission, [
                'discount_amount' => (float) $this->discountAmount,
                'use_installment_plan' => $this->useInstallmentPlan,
                'misc_fees' => $this->miscFees,
                'installment_plan' => $this->useInstallmentPlan ? $this->installmentPlan : [],
            ], Auth::user());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        Notification::make()
            ->title('Fee plan saved')
            ->body('Net fee is now ₹'.$this->admissionNetFee.'.')
            ->success()
            ->send();
    }

    /** @deprecated Use saveAdmissionFeePlan */
    public function saveAdmissionDiscount(AdmissionService $admissions): void
    {
        $this->saveAdmissionFeePlan($admissions);
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
        $this->record->loadMissing([
            'activeEnrollment.course',
            'activeEnrollment.feeStructure.installments',
            'activeEnrollment.feeStructure.miscCharges',
            'activeEnrollment.feeStructure.penalties.feeInstallment',
            'activeEnrollment.feeStructure.discountSetBy',
            'activeEnrollment.feeStructure.discountEntries.grantedBy',
            'activeEnrollment.feeStructure.setBy',
            'activeEnrollment.feeStructure.history.changedBy',
        ]);
        $feeStructure = $this->record->activeEnrollment?->feeStructure;

        $this->installments = $feeStructure?->installments ?? new Collection;
        $this->penalties = $feeStructure?->penalties ?? new Collection;
        $this->feeStructureHistory = $feeStructure
            ? $feeStructure->history()->with('changedBy')->orderByDesc('changed_at')->limit(20)->get()
            : new Collection;
        $this->loadPayments();
    }

    public function waivePenalty(int $penaltyId, string $reason, PenaltyCalculationService $penalties): void
    {
        abort_unless($this->userCan(CrmPermission::FeesWaivePenalty), 403);

        $penalty = FeePenalty::query()
            ->where('student_id', $this->record->id)
            ->findOrFail($penaltyId);

        $penalties->waive($penalty, Auth::user(), $reason);

        $this->feesTabLoaded = false;
        $this->loadFeesTab();

        Notification::make()
            ->title('Late fee waived')
            ->success()
            ->send();
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
            ->with(['addedBy.staffProfile', 'feeStructure.enrollment.course', 'feeInstallment'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(CrmPagination::PER_PAGE)
            ->get();
    }

    public function loadAttendanceTab(): void
    {
        if ($this->attendanceTabLoaded) {
            return;
        }

        $this->attendanceTabLoaded = true;
        $this->cachedProfileSummary = null;
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
            ->limit(CrmPagination::PER_PAGE)
            ->get();

        $this->attendancePercentage = app(AttendanceService::class)->percentageForStudent($this->record);
    }

    public function loadHomeworkTab(): void
    {
        if ($this->homeworkTabLoaded) {
            return;
        }

        $this->homeworkTabLoaded = true;
        $this->homeworkAssignments = app(HomeworkAssignmentService::class)
            ->assignmentsForStudentProfile($this->record);
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
        abort_unless($this->userCan(CrmPermission::StudentsEdit), 403);

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
        abort_unless($this->userCan(CrmPermission::FeesCollect), 403);

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
        abort_unless($this->userCan(CrmPermission::FeesAdjustStructure), 403);

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

        abort_unless(Auth::user()?->can('approve', $this->activeAdmission), 403);

        $enrollment = $admissions->approve($this->activeAdmission, Auth::user());

        $this->refreshRecord();
        $this->admissionTabLoaded = false;
        $this->feesTabLoaded = false;
        $this->loadAdmissionTab();
        $this->profileTab = 'fees';
        $this->loadFeesTab();

        Notification::make()
            ->title('Admission approved')
            ->body('Roll No. '.$enrollment->enrollment_number.' created. Set up fees in the Fees tab.')
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

        abort_unless(Auth::user()?->can('returnForCorrection', $this->activeAdmission), 403);

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

    protected function resolveAdmissionForDocuments(): ?Admission
    {
        $admission = $this->record->activeEnrollment?->admission
            ?? $this->record->admissions()->latest()->first();

        $admission?->load('documents');

        return $admission;
    }

    protected function refreshRecord(): void
    {
        $this->record->refresh()->load([
            'enquiries.course',
            'activeEnrollment.course',
            'activeEnrollment.feeStructure',
            'activeBatchStudent.batch.trainer',
        ]);

        $this->cachedProfileSummary = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSearch')
                ->label('Back to Search')
                ->url(StudentSearchPage::getUrl())
                ->color('gray')
                ->button()
                ->outlined(),
            Action::make('addVisit')
                ->label('Add Visit')
                ->icon('heroicon-o-plus-circle')
                ->button()
                ->color('primary')
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
                ->visible(fn (): bool => $this->licensed(LicenseFeature::Enquiries)
                    && $this->userCan(CrmPermission::LeadsCall)
                    && $this->record->enquiries->isNotEmpty()),
            Action::make('addEnquiry')
                ->label('Add Enquiry')
                ->icon('heroicon-o-document-plus')
                ->button()
                ->color('primary')
                ->outlined()
                ->form(EnquiryFormSchema::forExistingStudent(Auth::id()))
                ->action(function (array $data) {
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
                ->visible(fn (): bool => $this->licensed(LicenseFeature::Enquiries)
                    && $this->userCan(CrmPermission::LeadsCall)
                    && $this->record->enquiries()->doesntExist()),
            Action::make('convertToAdmission')
                ->label('Convert to Admission')
                ->icon('heroicon-o-arrow-right-circle')
                ->button()
                ->color('primary')
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
                ->extraModalFooterActions([
                    Action::make('suggestInstallmentPlan')
                        ->label('Suggest 50/50 plan')
                        ->color('gray')
                        ->action(function (Action $action): void {
                            $livewire = $action->getLivewire();
                            $mounted = $livewire->mountedActionsData[0] ?? [];

                            if (! is_array($mounted)) {
                                return;
                            }

                            $courseId = $mounted['course_id'] ?? null;
                            $discount = max(0, (float) ($mounted['discount_amount'] ?? 0));
                            $miscTotal = FeePlanCalculator::sumAmounts($mounted['misc_fees'] ?? []);

                            if (! $courseId) {
                                return;
                            }

                            $course = \App\Models\Course::query()->find($courseId);

                            if (! $course) {
                                return;
                            }

                            $net = round(max(0, (float) $course->fee - $discount + $miscTotal), 2);
                            $livewire->mountedActionsData[0]['use_installment_plan'] = true;
                            $livewire->mountedActionsData[0]['installment_plan'] = FeePlanCalculator::defaultTwoPartPlan($net);
                        }),
                    Action::make('fillInstallmentBalance')
                        ->label('Fill balance on last row')
                        ->color('gray')
                        ->action(function (Action $action): void {
                            $livewire = $action->getLivewire();
                            $mounted = $livewire->mountedActionsData[0] ?? [];

                            if (! is_array($mounted)) {
                                return;
                            }

                            $courseId = $mounted['course_id'] ?? null;
                            $discount = max(0, (float) ($mounted['discount_amount'] ?? 0));
                            $miscTotal = FeePlanCalculator::sumAmounts($mounted['misc_fees'] ?? []);
                            $plan = $mounted['installment_plan'] ?? [];

                            if (! $courseId || $plan === []) {
                                return;
                            }

                            $course = \App\Models\Course::query()->find($courseId);

                            if (! $course) {
                                return;
                            }

                            $net = round(max(0, (float) $course->fee - $discount + $miscTotal), 2);
                            $livewire->mountedActionsData[0]['installment_plan'] = FeePlanCalculator::fillBalanceOnLastRow($plan, $net);
                            $livewire->mountedActionsData[0]['use_installment_plan'] = true;
                        }),
                ])
                ->modalSubmitAction(function (Action $action): Action {
                    return $action->disabled(function (): bool {
                        $mounted = $this->mountedActionsData[0] ?? [];

                        if (! is_array($mounted)) {
                            return true;
                        }

                        return ! FeePlanSubmissionGuard::canSubmitConvert($mounted);
                    });
                })
                ->before(function (array $data, Action $action): void {
                    FeePlanSubmissionGuard::assertConvertable($data, $action);
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
                            'use_installment_plan' => $data['use_installment_plan'] ?? false,
                            'misc_fees' => $data['misc_fees'] ?? [],
                            'installment_plan' => $data['installment_plan'] ?? [],
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
                ->visible(fn (ConvertToAdmissionPresenter $presenter): bool => $this->licensed(LicenseFeature::Admissions)
                    && $this->userCan(CrmPermission::AdmissionsView)
                    && $presenter->convertibleEnquiries($this->record)->isNotEmpty()),
            Action::make('assignBatch')
                ->label('Assign Batch')
                ->icon('heroicon-o-user-group')
                ->button()
                ->color('gray')
                ->outlined()
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
                ->visible(fn (): bool => $this->userCan(CrmPermission::AttendanceMark)
                    && $this->record->activeEnrollment !== null),
            Action::make('addPayment')
                ->label('Add Payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->button()
                ->form(function (): array {
                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return [];
                    }

                    return AddPaymentFormSchema::fields($feeStructure);
                })
                ->action(function (array $data, PaymentService $payments): void {
                    abort_unless($this->userCan(CrmPermission::FeesCollect), 403);

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
                ->visible(fn (): bool => $this->licensed(LicenseFeature::Fees)
                    && $this->userCan(CrmPermission::FeesCollect)
                    && $this->record->activeEnrollment?->feeStructure !== null
                    && (float) ($this->record->activeEnrollment->feeStructure->totalCollectiblePending() ?? 0) > 0),
            Action::make('adjustFees')
                ->label('Adjust Fees')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->button()
                ->modalHeading('Adjust fee structure')
                ->modalDescription('Admin only. Fee changes, installment reschedules, and reasons are stored in fee history and the audit log.')
                ->fillForm(function (): array {
                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return [];
                    }

                    return AdjustFeeStructureFormSchema::initialState($feeStructure);
                })
                ->form(function (): array {
                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return [];
                    }

                    return AdjustFeeStructureFormSchema::fields($feeStructure);
                })
                ->extraModalFooterActions([
                    Action::make('fillAdjustInstallmentBalance')
                        ->label('Fill balance on last row')
                        ->color('gray')
                        ->action(function (Action $action): void {
                            $livewire = $action->getLivewire();
                            $mounted = $livewire->mountedActionsData[0] ?? [];
                            $feeStructure = $livewire->record->activeEnrollment?->feeStructure;

                            if (! is_array($mounted) || ! $feeStructure) {
                                return;
                            }

                            $feeStructure->loadMissing('miscCharges');
                            $miscTotal = $feeStructure->miscChargesTotal();
                            $target = AdjustFeeStructureFormSchema::scheduleTargetFromMounted(
                                $feeStructure,
                                $mounted,
                                $miscTotal,
                            );
                            $plan = $mounted['installment_plan'] ?? [];

                            if ($plan === []) {
                                return;
                            }

                            $livewire->mountedActionsData[0]['installment_plan'] = FeePlanCalculator::fillBalanceOnLastRow($plan, $target);
                            $livewire->mountedActionsData[0]['reschedule_installments'] = true;
                        }),
                ])
                ->modalSubmitAction(function (Action $action): Action {
                    return $action
                        ->label('Save fee changes')
                        ->disabled(function (): bool {
                            $mounted = $this->mountedActionsData[0] ?? [];
                            $feeStructure = $this->record->activeEnrollment?->feeStructure;

                            if (! is_array($mounted) || ! $feeStructure) {
                                return false;
                            }

                            return ! FeePlanSubmissionGuard::canSubmitAdjustFees($mounted, $feeStructure);
                        });
                })
                ->before(function (array $data, Action $action): void {
                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if ($feeStructure) {
                        FeePlanSubmissionGuard::assertAdjustFees($data, $feeStructure, $action);
                    }
                })
                ->action(function (array $data, FeeStructureService $fees): void {
                    abort_unless($this->userCan(CrmPermission::FeesAdjustStructure), 403);

                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return;
                    }

                    $fees->updateByAdmin(
                        $feeStructure,
                        AdjustFeeStructureFormSchema::resolveForSave($feeStructure, $data),
                        Auth::user(),
                    );
                    $this->refreshRecord();
                    $this->feesTabLoaded = false;
                    $this->loadFeesTab();

                    Notification::make()
                        ->title('Fees updated')
                        ->body('Fee structure revised and history recorded.')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->licensed(LicenseFeature::Fees)
                    && $this->userCan(CrmPermission::FeesAdjustStructure)
                    && $this->record->activeEnrollment?->feeStructure !== null),
            Action::make('editStudent')
                ->label('Edit Details')
                ->icon('heroicon-o-pencil-square')
                ->button()
                ->color('gray')
                ->outlined()
                ->modalHeading('Edit student details')
                ->form(function (): array {
                    $admission = $this->resolveAdmissionForDocuments();

                    return StudentProfileFormSchema::forEdit(
                        $this->record->activeEnrollment !== null,
                        $this->record->id,
                        $admission,
                        $this->record->activeEnrollment?->hasIdCard() ?? false,
                    );
                })
                ->fillForm(fn (): array => [
                    'name' => $this->record->name,
                    'father_name' => $this->record->father_name,
                    'date_of_birth' => $this->record->date_of_birth,
                    'gender' => $this->record->gender?->value,
                    'mobile' => $this->record->mobile,
                    'alternate_mobile' => $this->record->alternate_mobile,
                    'address' => $this->record->address,
                    'city' => $this->record->city,
                    'state' => $this->record->state,
                    'pincode' => $this->record->pincode,
                    'category' => $this->record->category?->value,
                    'enrollment_number' => $this->record->activeEnrollment?->enrollment_number,
                    'custom_data' => $this->record->custom_data ?? [],
                ])
                ->action(function (array $data, StudentUpdateService $studentUpdates, EnrollmentRollNumberService $rollNumbers, DocumentService $documents, IdCardService $idCards): void {
                    $regenerateIdCard = (bool) ($data['regenerate_id_card'] ?? false);
                    unset($data['regenerate_id_card']);

                    $documentFields = [
                        'photo' => DocumentType::Photo,
                        'aadhaar' => DocumentType::Aadhaar,
                        'marksheet' => DocumentType::Marksheet,
                        'signature' => DocumentType::Signature,
                    ];

                    $photoUpdated = false;
                    $documentsUpdated = false;
                    $admission = $this->resolveAdmissionForDocuments();

                    if ($admission) {
                        foreach ($documentFields as $field => $type) {
                            if (filled($data[$field] ?? null)) {
                                $documents->storeFromFilamentUpload(
                                    $admission,
                                    $type,
                                    $data[$field],
                                    Auth::user(),
                                );
                                $documentsUpdated = true;

                                if ($field === 'photo') {
                                    $photoUpdated = true;
                                }
                            }

                            unset($data[$field]);
                        }

                        if ($documentsUpdated) {
                            app(StorageCleanupService::class)->pruneLivewireTempFiles(0);
                            $this->documentsTabLoaded = false;
                        }
                    }

                    $rollNumber = $data['enrollment_number'] ?? null;
                    unset($data['enrollment_number']);

                    $this->record = $studentUpdates->update($this->record, $data, Auth::user());

                    $enrollment = $this->record->activeEnrollment;
                    $rollNumberChanged = false;
                    $idCardRegenerated = false;

                    if ($enrollment && filled($rollNumber)) {
                        $normalized = strtoupper(trim($rollNumber));

                        if ($normalized !== $enrollment->enrollment_number) {
                            $rollNumbers->update($enrollment, $normalized, Auth::user());
                            $rollNumberChanged = true;
                        }
                    }

                    if ($photoUpdated && $regenerateIdCard && $enrollment?->hasIdCard() && $enrollment->canGenerateIdCard()) {
                        $idCards->generateForEnrollment($enrollment, Auth::user(), regenerate: true);
                        $idCardRegenerated = true;
                    }

                    if ($photoUpdated || $documentsUpdated || $rollNumberChanged) {
                        $this->refreshRecord();
                    }

                    $body = match (true) {
                        $idCardRegenerated && $rollNumberChanged => 'Profile, documents, and roll number saved. ID card regenerated with the new photo.',
                        $idCardRegenerated => 'Profile and documents saved. ID card regenerated with the new photo.',
                        $rollNumberChanged && $photoUpdated => 'Profile, photo, and roll number saved successfully.',
                        $rollNumberChanged => 'Profile and roll number saved successfully.',
                        $photoUpdated => 'Photo updated. Turn on “Regenerate ID card” to refresh the PDF, or use Regenerate on the profile.',
                        $documentsUpdated => 'Documents updated successfully.',
                        default => 'Profile details saved successfully.',
                    };

                    Notification::make()
                        ->title('Student updated')
                        ->body($body)
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->userCan(CrmPermission::StudentsEdit)),
        ];
    }

    protected function pendingCallMatchesStudent(int $studentId): bool
    {
        return (int) $this->record->id === $studentId;
    }

    public function submitLogCall(CallLogService $callLog): void
    {
        if (! $this->persistLogCall($this->record, $callLog)) {
            return;
        }

        $this->showLogCallModal = false;
        $this->resetLogCallForm();
        $this->refreshRecord();
        $this->callsTabLoaded = false;
        $this->visitsTabLoaded = false;
        $this->loadCallsTab();
        $this->loadVisitsTab();
        $this->profileTab = 'calls';

        Notification::make()
            ->title('Call logged')
            ->body('Call saved under your name in the Calls tab.')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.log-call-modal')
                ->viewData(fn (): array => [
                    'showLogCallModal' => $this->showLogCallModal,
                    'logCallForm' => $this->logCallForm,
                    'logCallModalMode' => 'profile',
                    'logCallLeadName' => $this->record->name,
                    'logCallLeadPhone' => $this->record->mobile,
                ]),
            View::make('filament.pages.partials.student-id-card-preview-modal')
                ->viewData(fn (): array => [
                    'record' => $this->record,
                    'showIdCardPreview' => $this->showIdCardPreview,
                ]),
            View::make('filament.pages.partials.student-profile-header')
                ->viewData(fn (): array => [
                    'record' => $this->record,
                    'profile' => $this->profileSummary(),
                ]),
            Tabs::make('Student Profile')
                ->livewireProperty('profileTab')
                ->extraAttributes(['class' => 'fi-student-profile-tabs'])
                ->tabs([
                    'overview' => Tab::make('Overview')
                        ->icon('heroicon-o-squares-2x2')
                        ->schema([
                            View::make('filament.pages.partials.student-profile-overview')
                                ->viewData(fn (): array => [
                                    'record' => $this->record,
                                    'enquiries' => $this->record->enquiries,
                                    'profile' => $this->profileSummary(),
                                ]),
                        ]),
                    'visits' => Tab::make('Visits')
                        ->icon('heroicon-o-calendar-days')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::Enquiries))
                        ->schema([
                            View::make('filament.pages.partials.student-profile-visits')
                                ->viewData(fn (): array => [
                                    'visitsTabLoaded' => $this->visitsTabLoaded,
                                    'visits' => $this->visits,
                                ]),
                        ]),
                    'calls' => Tab::make('Calls')
                        ->icon('heroicon-o-phone')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::Calls))
                        ->schema([
                            View::make('filament.pages.partials.student-profile-calls')
                                ->viewData(fn (): array => [
                                    'callsTabLoaded' => $this->callsTabLoaded,
                                    'calls' => $this->calls,
                                ]),
                        ]),
                    'messages' => Tab::make('Messages')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::WhatsApp))
                        ->schema([
                            View::make('filament.pages.partials.student-profile-messages')
                                ->viewData(fn (): array => [
                                    'record' => $this->record,
                                    'messagesTabLoaded' => $this->messagesTabLoaded,
                                    'whatsappMessages' => $this->whatsappMessages,
                                ]),
                        ]),
                    'admission' => Tab::make('Admission')
                        ->icon('heroicon-o-document-text')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::Admissions))
                        ->schema([
                            View::make('filament.pages.partials.student-profile-admission')
                                ->viewData(fn (): array => [
                                    'admissionTabLoaded' => $this->admissionTabLoaded,
                                    'activeAdmission' => $this->activeAdmission,
                                    'record' => $this->record,
                                ]),
                        ]),
                    'documents' => Tab::make('Documents')
                        ->icon('heroicon-o-folder')
                        ->schema([
                            View::make('filament.pages.partials.student-profile-documents')
                                ->viewData(fn (): array => [
                                    'documentsTabLoaded' => $this->documentsTabLoaded,
                                    'documents' => $this->documents,
                                ]),
                        ]),
                    'fees' => Tab::make('Fees')
                        ->icon('heroicon-o-banknotes')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::Fees)
                            && $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-fees')
                                ->viewData(fn (): array => [
                                    'record' => $this->record->loadMissing([
                                        'activeEnrollment.course',
                                        'activeEnrollment.feeStructure.installments',
                                        'activeEnrollment.feeStructure.miscCharges',
                                    ]),
                                    'feesTabLoaded' => $this->feesTabLoaded,
                                    'payments' => $this->payments,
                                    'installments' => $this->installments,
                                    'penalties' => $this->penalties,
                                    'feeStructureHistory' => $this->feeStructureHistory,
                                    'canCollectFees' => $this->userCan(CrmPermission::FeesCollect),
                                    'canWaivePenalty' => $this->userCan(CrmPermission::FeesWaivePenalty),
                                ]),
                        ]),
                    'receipts' => Tab::make('Receipts')
                        ->icon('heroicon-o-receipt-percent')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::Fees)
                            && $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-receipts')
                                ->viewData(fn (): array => [
                                    'receiptsTabLoaded' => $this->receiptsTabLoaded,
                                    'payments' => $this->payments,
                                    'canCollectFees' => $this->userCan(CrmPermission::FeesCollect),
                                    'canAdjustFees' => $this->userCan(CrmPermission::FeesAdjustStructure),
                                ]),
                        ]),
                    'attendance' => Tab::make('Attendance')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::Attendance)
                            && $this->record->activeEnrollment !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-attendance')
                                ->viewData(fn (): array => [
                                    'attendanceTabLoaded' => $this->attendanceTabLoaded,
                                    'activeBatch' => $this->activeBatch,
                                    'attendanceRecords' => $this->attendanceRecords,
                                    'attendancePercentage' => $this->attendancePercentage,
                                ]),
                        ]),
                    'homework' => Tab::make('Homework')
                        ->icon('heroicon-o-book-open')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::Homework)
                            && $this->record->activeBatchStudent !== null)
                        ->schema([
                            View::make('filament.pages.partials.student-profile-homework')
                                ->viewData(fn (): array => [
                                    'homeworkTabLoaded' => $this->homeworkTabLoaded,
                                    'assignments' => $this->homeworkAssignments,
                                    'portalUrl' => route('portal.homework.index'),
                                ]),
                        ]),
                    'activities' => Tab::make('Activities')
                        ->icon('heroicon-o-academic-cap')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::Marks)
                            && $this->record->activeEnrollment !== null
                            && $this->enabledActivityTypes()->isNotEmpty())
                        ->schema([
                            View::make('filament.pages.partials.student-profile-activities-hub')
                                ->viewData(fn (): array => [
                                    'activityTypes' => $this->enabledActivityTypes(),
                                    'selectedSlug' => $this->activitySubTab,
                                    'loaded' => $this->activityTabLoaded,
                                    'records' => $this->activityRecords,
                                ]),
                        ]),
                ]),
        ]);
    }
}
