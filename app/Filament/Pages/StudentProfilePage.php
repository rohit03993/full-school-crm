<?php

namespace App\Filament\Pages;

use App\Enums\CallStatus;
use App\Enums\BatchStatus;
use App\Enums\CampusVisitPurpose;
use App\Enums\LeadSource;
use App\Models\ActivityAttendance;
use App\Models\ActivityType;
use App\Enums\CrmPermission;
use App\Enums\DocumentType;
use App\Enums\FeeMiscChargeAdjustmentType;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Enums\WhatsAppRecipientStatus;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Support\CrmPagination;
use App\Models\Attendance;
use App\Models\Batch;
use App\Filament\Concerns\HandlesCloseMeetingModal;
use App\Filament\Concerns\HandlesLogCallModal;
use App\Filament\Forms\AdjustFeeStructureFormSchema;
use App\Filament\Forms\AdmissionFeePlanFormSchema;
use App\Filament\Forms\AddPaymentFormSchema;
use App\Filament\Forms\ConvertToAdmissionFormSchema;
use App\Filament\Forms\EnquiryFormSchema;
use App\Filament\Forms\PayMiscChargeFormSchema;
use App\Filament\Forms\StudentProfileFormSchema;
use App\Models\Admission;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\FeeMiscCharge;
use App\Models\FeeMiscChargeAdjustmentRequest;
use App\Models\FeePenalty;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\StudentCase;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityAttendanceService;
use App\Services\AdmissionFeePlanService;
use App\Services\AdmissionService;
use App\Services\AttendanceService;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use App\Enums\ReportType;
use App\Services\BatchService;
use App\Services\CallLogService;
use App\Services\ConvertToAdmissionPresenter;
use App\Services\CourseFeeSyncService;
use App\Services\DocumentService;
use App\Services\EnquiryService;
use App\Services\EnrollmentPlacementService;
use App\Services\EnrollmentRollNumberService;
use App\Services\FeeDiscountHistoryService;
use App\Services\FeeInstallmentService;
use App\Services\FeeMiscChargeAdjustmentService;
use App\Services\FeeMiscChargeService;
use App\Services\FeeStructureService;
use App\Services\HomeworkAssignmentService;
use App\Services\IdCardService;
use App\Services\LeadAssignmentService;
use App\Services\PaymentService;
use App\Services\PenaltyCalculationService;
use App\Services\ReceiptService;
use App\Services\StorageCleanupService;
use App\Services\StudentCaseService;
use App\Services\StudentCounterService;
use App\Services\StudentUpdateService;
use App\Services\MetaWhatsAppInboxService;
use App\Services\StudentWhatsAppThreadService;
use App\Services\WhatsAppProviderResolver;
use App\Support\StudentWhatsAppThreadItem;
use App\Support\StudentWhatsAppTemplateComposer;
use App\Services\VisitMeetingAssignmentService;
use App\Services\WhatsAppCampaignService;
use App\Services\WhatsAppTemplateCatalog;
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
    use HandlesCloseMeetingModal;
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

    public bool $casesTabLoaded = false;

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

    /** @var array{percentage: float, present_days: int, leave_days: int, credited_days: int, expected_days: int, absent_days?: int, period_label: string, from?: string, to?: string}|null */
    public ?array $attendanceSummary = null;

    public string $attendanceMonth = '';

    public int $attendancePage = 1;

    public int $attendanceTotal = 0;

    public int $attendanceLastPage = 1;

    public string $paymentsMonth = '';

    public int $paymentsPage = 1;

    public int $paymentsTotal = 0;

    public int $paymentsLastPage = 1;

    /**
     * @var array<int, Collection<int, ActivityAttendance>>
     */
    public array $activityRecords = [];

    /**
     * @var Collection<int, Visit>
     */
    public Collection $visits;

    /**
     * @var array<int, int>
     */
    public array $visitSequenceById = [];

    /**
     * @var \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public \Illuminate\Support\Collection $leadTimeline;

    /**
     * @var Collection<int, StudentCall>
     */
    public Collection $calls;

    /**
     * @var Collection<int, StudentCase>
     */
    public Collection $cases;

    public ?int $expandedCaseId = null;

    public string $caseTransferNote = '';

    public ?int $caseTransferAssigneeId = null;

    public string $caseClosingNote = '';

    public bool $showOpenCaseForm = false;

    public ?string $openCaseType = null;

    public string $openCaseTitle = '';

    public string $openCaseSummary = '';

    public ?int $openCaseAssigneeId = null;

    public string $openCaseHandoffNote = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $openCaseBanners = [];

    /**
     * @var array<int, array{key: string, source: string, direction: string, body: string, status: string, statusLabel: string, at: ?string, at_label: ?string, templateName: ?string, provider: ?string}>
     */
    public array $messageThread = [];

    public string $metaReplyText = '';

    public ?TemporaryUploadedFile $metaReplyAttachment = null;

    public bool $showMetaReplyAttachment = false;

    public bool $metaSessionOpen = false;

    public bool $metaRoutingActive = false;

    public string $whatsappProviderLabel = 'Pal Digital';

    /**
     * @var Collection<int, \App\Models\HomeworkAssignment>
     */
    public Collection $homeworkAssignments;

    public ?int $sendWhatsAppTemplateId = null;

    /** @var array<int, string> */
    public array $sendWhatsAppTemplateParams = [];

    /** @var list<array{index: int, label: string, hint: string, placeholder: string}> */
    public array $sendWhatsAppTemplateFields = [];

    public int $sendWhatsAppTemplateParamCount = 0;

    public ?string $sendWhatsAppTemplatePreview = null;

    public ?string $sendWhatsAppSelectedTemplateName = null;

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
            'activeEnrollment.admission.enquiry',
            'activeBatchStudent.batch.trainer',
            'lastCall.staff',
        ])->loadCount([
            'calls as not_connected_attempts_count' => fn ($query) => $query->whereIn(
                'call_status',
                CallStatus::notConnectedValues(),
            ),
        ]);

        $this->visits = new Collection;
        $this->leadTimeline = collect();
        $this->calls = new Collection;
        $this->cases = new Collection;
        $this->openCaseBanners = [];
        $this->messageThread = [];
        $this->homeworkAssignments = new Collection;
        $this->documents = new Collection;
        $this->payments = new Collection;
        $this->installments = new Collection;
        $this->feeStructureHistory = new Collection;
        $this->penalties = new Collection;
        $this->attendanceRecords = new Collection;
        $this->activityRecords = [];
        $this->attendanceMonth = now()->format('Y-m');
        $this->attendancePage = 1;
        $this->paymentsMonth = '';
        $this->paymentsPage = 1;

        $tab = request()->query('tab');

        if ($tab === 'admission') {
            $tab = 'documents';
        }

        if (is_string($tab)) {
            if (str_starts_with($tab, 'activity_')) {
                $this->profileTab = 'activities';
                $this->activitySubTab = substr($tab, strlen('activity_'));
            } elseif (in_array($tab, $this->validProfileTabs(), true)) {
                $this->profileTab = $tab;
            }

            $this->updatedProfileTab();
        }

        $caseId = request()->query('case');

        if (is_numeric($caseId)) {
            $this->expandedCaseId = (int) $caseId;

            if (in_array('cases', $this->validProfileTabs(), true)) {
                $this->profileTab = 'cases';
                $this->loadCasesTab();
            }
        }

        if (! in_array($this->profileTab, $this->validProfileTabs(), true)) {
            $this->profileTab = 'overview';
        }

        $this->syncCatalogCourseFeeIfNeeded();
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
        if ($this->record->activeEnrollment !== null) {
            return null;
        }

        return CrmHint::text('students.profile');
    }

    public function updatedProfileTab(): void
    {
        if ($this->profileTab !== 'messages') {
            $this->showMetaReplyAttachment = false;
            $this->metaReplyAttachment = null;
        }

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
            'cases' => $this->loadCasesTab(),
            'messages' => $this->loadMessagesTab(),
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

        if ($this->record->activeEnrollment !== null && $this->userCan(CrmPermission::CasesView)) {
            $tabs[] = 'cases';
        }

        if ($this->licensed(LicenseFeature::WhatsApp)) {
            $tabs[] = 'messages';
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
        return $this->cachedEnabledActivityTypes ??= ActivityType::scoringTypes();
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
            $this->cachedProfileSummary['meeting_assignment'] = app(VisitMeetingAssignmentService::class)
                ->profileMeetingAssignment($this->record, Auth::user());
            $this->cachedProfileSummary['open_cases'] = app(StudentCaseService::class)
                ->overviewBanners($this->record, Auth::user());
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

        $timelineService = app(\App\Services\LeadTimelineService::class);

        $this->visitSequenceById = $timelineService->visitSequenceMap($this->record);
        $this->leadTimeline = $timelineService->forStudent($this->record);
    }

    public function loadCasesTab(): void
    {
        if ($this->casesTabLoaded) {
            return;
        }

        $this->casesTabLoaded = true;

        $this->cases = app(StudentCaseService::class)->forStudent($this->record);
        $this->openCaseBanners = app(StudentCaseService::class)->overviewBanners($this->record, Auth::user());
    }

    public function toggleCase(int $caseId): void
    {
        $this->expandedCaseId = $this->expandedCaseId === $caseId ? null : $caseId;
        $this->caseTransferNote = '';
        $this->caseTransferAssigneeId = null;
        $this->caseClosingNote = '';
    }

    public function openOpenCaseForm(): void
    {
        $cases = app(StudentCaseService::class);

        if (! $cases->canOpenAsAdmin(Auth::user(), $this->record)) {
            Notification::make()
                ->title('Not allowed')
                ->body('Only Super Admin can open a case from the student profile.')
                ->warning()
                ->send();

            return;
        }

        $this->openCaseType = CampusVisitPurpose::General->value;
        $this->openCaseTitle = '';
        $this->openCaseSummary = '';
        $this->openCaseAssigneeId = null;
        $this->openCaseHandoffNote = '';
        $this->showOpenCaseForm = true;
    }

    public function cancelOpenCaseForm(): void
    {
        $this->showOpenCaseForm = false;
    }

    public function submitOpenCase(StudentCaseService $cases): void
    {
        if (! $cases->canOpenAsAdmin(Auth::user(), $this->record)) {
            Notification::make()
                ->title('Not allowed')
                ->body('Only Super Admin can open a case from the student profile.')
                ->warning()
                ->send();

            return;
        }

        $caseType = CampusVisitPurpose::tryFrom((string) $this->openCaseType);
        $assignee = User::query()->find((int) $this->openCaseAssigneeId);

        if (! $caseType || ! $assignee) {
            Notification::make()
                ->title('Missing details')
                ->body('Select a case type and staff assignee.')
                ->warning()
                ->send();

            return;
        }

        try {
            $case = $cases->open(
                $this->record->fresh(['activeEnrollment']),
                $caseType,
                $this->openCaseTitle,
                filled($this->openCaseSummary) ? $this->openCaseSummary : null,
                $assignee,
                Auth::user(),
                filled($this->openCaseHandoffNote) ? $this->openCaseHandoffNote : null,
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not open case')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                ->danger()
                ->send();

            return;
        }

        $this->showOpenCaseForm = false;
        $this->invalidateCasesTab();
        $this->expandedCaseId = $case->id;

        Notification::make()
            ->title('Case opened')
            ->body("{$case->case_number} assigned to {$assignee->name}.")
            ->success()
            ->send();
    }

    public function submitCaseTransfer(int $caseId, StudentCaseService $cases): void
    {
        $case = StudentCase::query()->whereKey($caseId)->where('student_id', $this->record->id)->firstOrFail();
        $assignee = User::query()->findOrFail((int) $this->caseTransferAssigneeId);

        try {
            $cases->transfer($case, $assignee, Auth::user(), $this->caseTransferNote);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not transfer case')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                ->danger()
                ->send();

            return;
        }

        $this->invalidateCasesTab();
        $this->expandedCaseId = $caseId;

        Notification::make()
            ->title($cases->canReassignAsAdmin($case, Auth::user()) && ! $cases->isCurrentAssignee($case, Auth::user())
                ? 'Case reassigned'
                : 'Case transferred')
            ->body("Assigned to {$assignee->name}.")
            ->success()
            ->send();
    }

    public function submitCaseClose(int $caseId, StudentCaseService $cases): void
    {
        $case = StudentCase::query()->whereKey($caseId)->where('student_id', $this->record->id)->firstOrFail();

        try {
            $cases->close($case, Auth::user(), $this->caseClosingNote);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not close case')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                ->danger()
                ->send();

            return;
        }

        $this->invalidateCasesTab();
        $this->expandedCaseId = null;

        Notification::make()
            ->title('Case closed')
            ->success()
            ->send();
    }

    public function openLogCallForCase(int $caseId): void
    {
        $case = StudentCase::query()->whereKey($caseId)->where('student_id', $this->record->id)->firstOrFail();

        if (! $case->isOpen()) {
            Notification::make()
                ->title('Case is closed')
                ->warning()
                ->send();

            return;
        }

        if (! app(StudentCaseService::class)->canLogCall($case, Auth::user())) {
            Notification::make()
                ->title('Not assigned to this case')
                ->body('Only '.$case->currentAssignee?->name.' can log calls, transfer, or close this case.')
                ->warning()
                ->send();

            return;
        }

        $this->logCallStudentCaseId = $case->id;
        $this->openLogCallModal();
    }

    protected function invalidateCasesTab(): void
    {
        $this->casesTabLoaded = false;
        $this->cachedProfileSummary = null;
        $this->loadCasesTab();
    }

    public function loadCallsTab(): void
    {
        if ($this->callsTabLoaded) {
            return;
        }

        $this->callsTabLoaded = true;
        $this->calls = $this->record->calls()
            ->with(['staff', 'enquiry.course', 'studentCase'])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->limit(CrmPagination::PER_PAGE)
            ->get();
    }

    public function updatedSendWhatsAppTemplateId(): void
    {
        $this->refreshWhatsAppTemplateComposer();
    }

    public function updatedSendWhatsAppTemplateParams($value, $key = null): void
    {
        $this->refreshWhatsAppTemplatePreview();
    }

    protected function refreshWhatsAppTemplateComposer(): void
    {
        $this->sendWhatsAppTemplateFields = [];
        $this->sendWhatsAppTemplateParams = [];
        $this->sendWhatsAppTemplateParamCount = 0;
        $this->sendWhatsAppTemplatePreview = null;
        $this->sendWhatsAppSelectedTemplateName = null;

        if (! $this->sendWhatsAppTemplateId) {
            return;
        }

        $template = app(WhatsAppTemplateCatalog::class)->findSelectableTemplate((int) $this->sendWhatsAppTemplateId);

        if (! $template) {
            return;
        }

        $compose = app(StudentWhatsAppTemplateComposer::class)->compose(
            $template,
            $this->record,
            Auth::user(),
        );

        $this->sendWhatsAppTemplateFields = $compose['fields'];
        $this->sendWhatsAppTemplateParams = $compose['defaults'];
        $this->sendWhatsAppTemplateParamCount = $compose['param_count'];
        $this->sendWhatsAppTemplatePreview = $compose['preview_body'];
        $this->sendWhatsAppSelectedTemplateName = $compose['template_name'];
    }

    protected function refreshWhatsAppTemplatePreview(): void
    {
        if (! $this->sendWhatsAppTemplateId) {
            $this->sendWhatsAppTemplatePreview = null;

            return;
        }

        $template = app(WhatsAppTemplateCatalog::class)->findSelectableTemplate((int) $this->sendWhatsAppTemplateId);

        if (! $template) {
            return;
        }

        $this->sendWhatsAppTemplatePreview = app(StudentWhatsAppTemplateComposer::class)->preview(
            $template,
            $this->sendWhatsAppTemplateParams,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function whatsAppMessagesViewData(): array
    {
        try {
            $catalog = app(WhatsAppTemplateCatalog::class);

            return [
                'record' => $this->record,
                'messagesTabLoaded' => $this->messagesTabLoaded,
                'messageThread' => $this->messageThread,
                'metaSessionOpen' => $this->metaSessionOpen,
                'metaRoutingActive' => $this->metaRoutingActive,
                'whatsappProviderLabel' => $this->whatsappProviderLabel,
                'metaReplyText' => $this->metaReplyText,
                'showMetaReplyAttachment' => $this->showMetaReplyAttachment,
                'metaReplyAttachment' => $this->metaReplyAttachment,
                'waTemplates' => $catalog->selectableTemplates(),
                'waTemplateSyncHint' => $this->whatsAppTemplateSyncHint($catalog),
                'sendWhatsAppTemplateId' => $this->sendWhatsAppTemplateId,
                'sendWhatsAppTemplateFields' => $this->sendWhatsAppTemplateFields,
                'sendWhatsAppTemplateParamCount' => $this->sendWhatsAppTemplateParamCount,
                'sendWhatsAppTemplatePreview' => $this->sendWhatsAppTemplatePreview,
                'sendWhatsAppSelectedTemplateName' => $this->sendWhatsAppSelectedTemplateName,
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'record' => $this->record,
                'messagesTabLoaded' => true,
                'messageThread' => [],
                'metaSessionOpen' => false,
                'metaRoutingActive' => false,
                'whatsappProviderLabel' => 'Unavailable',
                'metaReplyText' => $this->metaReplyText,
                'showMetaReplyAttachment' => false,
                'metaReplyAttachment' => null,
                'waTemplates' => collect(),
                'waTemplateSyncHint' => null,
                'sendWhatsAppTemplateId' => null,
                'sendWhatsAppTemplateFields' => [],
                'sendWhatsAppTemplateParamCount' => 0,
                'sendWhatsAppTemplatePreview' => null,
                'sendWhatsAppSelectedTemplateName' => null,
            ];
        }
    }

    public function enableMetaReplyAttachment(): void
    {
        $this->showMetaReplyAttachment = true;
    }

    public function refreshThreadMedia(): void
    {
        if ($this->profileTab !== 'messages' || ! $this->messagesTabLoaded) {
            return;
        }

        $this->messagesTabLoaded = false;
        $this->loadMessagesTab();
    }

    protected function whatsAppTemplateSyncHint(WhatsAppTemplateCatalog $catalog): ?string
    {
        if (! $this->metaRoutingActive) {
            return null;
        }

        $orphaned = $catalog->orphanedPalTemplateNames();

        if ($orphaned === []) {
            return null;
        }

        return 'Only Meta-synced templates can be sent. Templates like «'
            .implode('», «', array_slice($orphaned, 0, 3))
            .'» are from an old import — open Connection & Setup and click Sync templates.';
    }

    public function loadMessagesTab(): void
    {
        if ($this->profileTab !== 'messages' || $this->messagesTabLoaded) {
            return;
        }

        $this->messagesTabLoaded = true;

        try {
            $threadService = app(StudentWhatsAppThreadService::class);
            $resolver = app(WhatsAppProviderResolver::class);

            $this->messageThread = $threadService->threadForStudent($this->record)
                ->map(fn (StudentWhatsAppThreadItem $item): array => $item->toArray())
                ->values()
                ->all();
            $this->metaSessionOpen = $threadService->sessionOpenForStudent($this->record);
            $this->metaRoutingActive = $resolver->metaOverridesPalDigital();
            $this->whatsappProviderLabel = $resolver->activeProviderLabel();
        } catch (\Throwable $exception) {
            report($exception);

            $this->messageThread = [];
            $this->metaSessionOpen = false;
            $this->metaRoutingActive = false;
            $this->whatsappProviderLabel = 'Unavailable';
        }
    }

    public function sendMetaReply(): void
    {
        if (! $this->licensed(LicenseFeature::WhatsApp)) {
            Notification::make()->title('WhatsApp module is not enabled')->warning()->send();

            return;
        }

        $result = app(MetaWhatsAppInboxService::class)->sendReply(
            $this->record,
            $this->metaReplyText,
            Auth::user(),
        );

        if ($result['status'] !== 'success') {
            Notification::make()
                ->title('Could not send reply')
                ->body((string) ($result['error'] ?? 'Unknown error'))
                ->danger()
                ->send();

            return;
        }

        $this->metaReplyText = '';
        $this->messagesTabLoaded = false;
        $this->loadMessagesTab();

        Notification::make()
            ->title('Reply sent')
            ->body('Your message was delivered via Meta WhatsApp.')
            ->success()
            ->send();
    }

    public function sendMetaMedia(): void
    {
        if (! $this->licensed(LicenseFeature::WhatsApp)) {
            Notification::make()->title('WhatsApp module is not enabled')->warning()->send();

            return;
        }

        if (! $this->metaReplyAttachment) {
            Notification::make()->title('Choose a file first')->warning()->send();

            return;
        }

        $result = app(MetaWhatsAppInboxService::class)->sendMedia(
            $this->record,
            $this->metaReplyAttachment,
            $this->metaReplyText,
            Auth::user(),
        );

        if ($result['status'] !== 'success') {
            Notification::make()
                ->title('Could not send attachment')
                ->body((string) ($result['error'] ?? 'Unknown error'))
                ->danger()
                ->send();

            return;
        }

        $this->metaReplyText = '';
        $this->metaReplyAttachment = null;
        $this->showMetaReplyAttachment = false;
        $this->messagesTabLoaded = false;
        $this->loadMessagesTab();

        Notification::make()
            ->title('Attachment sent')
            ->body('Your file was delivered via Meta WhatsApp.')
            ->success()
            ->send();
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

        $template = app(WhatsAppTemplateCatalog::class)->findSelectableTemplate((int) $this->sendWhatsAppTemplateId);

        if (! $template) {
            Notification::make()
                ->title('Template not available')
                ->body('This template is not synced for Meta. Open Connection & Setup and click Sync templates.')
                ->danger()
                ->send();

            return;
        }

        if ($this->sendWhatsAppTemplateParamCount > 0) {
            for ($i = 0; $i < $this->sendWhatsAppTemplateParamCount; $i++) {
                if (blank($this->sendWhatsAppTemplateParams[$i] ?? null)) {
                    $label = $this->sendWhatsAppTemplateFields[$i]['label'] ?? ('Parameter '.($i + 1));

                    Notification::make()
                        ->title('Fill all template fields')
                        ->body('Enter a value for «'.$label.'» before sending.')
                        ->warning()
                        ->send();

                    return;
                }
            }
        }

        $recipient = app(WhatsAppCampaignService::class)->sendSingle(
            $this->record,
            $template,
            Auth::user(),
            $this->sendWhatsAppTemplateParams,
        );

        $this->messagesTabLoaded = false;
        $this->loadMessagesTab();
        $this->sendWhatsAppTemplateId = null;
        $this->refreshWhatsAppTemplateComposer();

        if ($recipient->status === WhatsAppRecipientStatus::Failed) {
            Notification::make()
                ->title('WhatsApp failed')
                ->body($recipient->error_message ?: 'Meta rejected the message. Check Delivery log on the campaign page.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('WhatsApp sent')
            ->body('Message delivered to '.$this->record->mobile.'.')
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
        if (! $this->licensed(LicenseFeature::Fees) || ! $this->userCan(CrmPermission::FeesAdjustStructure)) {
            return false;
        }

        $admission = $this->activeAdmission ?? $this->record->admissions()
            ->whereDoesntHave('enrollment')
            ->latest()
            ->first();

        return (bool) $admission?->canAdjustFees();
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
        if ($this->licensed(LicenseFeature::Admissions)) {
            $this->loadAdmissionTab();
        }

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

        try {
            $this->syncCatalogCourseFeeIfNeeded();
            $this->record->loadMissing(array_merge([
                'activeEnrollment.course',
                'activeEnrollment.feeStructure.installments',
                'activeEnrollment.feeStructure.penalties.feeInstallment',
                'activeEnrollment.feeStructure.discountSetBy',
                'activeEnrollment.feeStructure.discountEntries.grantedBy',
                'activeEnrollment.feeStructure.setBy',
                'activeEnrollment.feeStructure.history.changedBy',
            ], $this->miscChargesEagerLoadPaths()));
            $feeStructure = $this->record->activeEnrollment?->feeStructure;

            $this->installments = $feeStructure
                ? $feeStructure->installments
                    ->sortBy(fn (\App\Models\FeeInstallment $row): array => [
                        $row->due_date?->timestamp ?? PHP_INT_MAX,
                        $row->sort_order,
                        $row->id,
                    ])
                    ->values()
                : new Collection;
            $this->penalties = $feeStructure?->penalties ?? new Collection;
            $this->feeStructureHistory = $feeStructure
                ? $feeStructure->history()->with('changedBy')->orderByDesc('changed_at')->limit(20)->get()
                : new Collection;
            $this->loadPayments();
            $this->feesTabLoaded = true;
        } catch (\Throwable $exception) {
            $this->feesTabLoaded = false;

            Notification::make()
                ->title('Could not load fees tab')
                ->body(\App\Support\CrmLivewireErrors::messageFor($exception))
                ->danger()
                ->persistent()
                ->send();

            report($exception);
        }
    }

    public function openPayMiscCharge(int $chargeId): void
    {
        $this->mountAction('addPayment', ['miscChargeId' => $chargeId]);
    }

    public function submitMiscChargeAdjustmentRequest(
        int $chargeId,
        string $type,
        ?float $discountAmount,
        string $reason,
        FeeMiscChargeService $miscCharges,
        FeeMiscChargeAdjustmentService $adjustments,
    ): void {
        abort_unless($this->userCanRequestMiscAdjustment(), 403);

        $reason = trim($reason);

        if ($reason === '') {
            Notification::make()
                ->title('Reason required')
                ->body('Enter why this discount or waive-off is needed.')
                ->warning()
                ->send();

            return;
        }

        $charge = $miscCharges->resolveForStudent($this->record, $chargeId);

        try {
            $adjustmentType = FeeMiscChargeAdjustmentType::from($type);
            $adjustments->submitRequest($charge, Auth::user(), $adjustmentType, $discountAmount, $reason);

            $this->feesTabLoaded = false;
            $this->loadFeesTab();

            Notification::make()
                ->title('Request sent to admin')
                ->body('Super Admin will review your '.$adjustmentType->label().' request.')
                ->success()
                ->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not submit request')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    protected function userCanRequestMiscAdjustment(): bool
    {
        if (! FeeMiscChargeAdjustmentRequest::schemaReady()) {
            return false;
        }

        if ($this->userIsSuperAdmin()) {
            return true;
        }

        return $this->userCan(CrmPermission::FeesWaivePenalty)
            || $this->userCan(CrmPermission::FeesAdjustStructure);
    }

    /**
     * @return list<string>
     */
    protected function miscChargesEagerLoadPaths(): array
    {
        if (FeeMiscChargeAdjustmentRequest::schemaReady()) {
            return ['activeEnrollment.feeStructure.miscCharges.adjustmentRequests.requestedBy'];
        }

        return ['activeEnrollment.feeStructure.miscCharges'];
    }

    protected function userIsSuperAdmin(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
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
        $query = Payment::query()
            ->where('student_id', $this->record->id)
            ->with(['addedBy.staffProfile', 'feeStructure.enrollment.course', 'feeInstallment', 'feeMiscCharge']);

        if (filled($this->paymentsMonth) && preg_match('/^\d{4}-\d{2}$/', $this->paymentsMonth)) {
            $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $this->paymentsMonth)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()]);
        }

        $this->paymentsTotal = (clone $query)->count();
        $perPage = CrmPagination::PER_PAGE;
        $this->paymentsLastPage = max(1, (int) ceil($this->paymentsTotal / $perPage));
        $this->paymentsPage = min(max(1, $this->paymentsPage), $this->paymentsLastPage);

        $this->payments = $query
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->forPage($this->paymentsPage, $perPage)
            ->get();
    }

    public function updatedPaymentsMonth(): void
    {
        $this->paymentsPage = 1;
        if ($this->feesTabLoaded || $this->receiptsTabLoaded) {
            $this->loadPayments();
        }
    }

    public function previousPaymentsPage(): void
    {
        $this->paymentsPage = max(1, $this->paymentsPage - 1);
        $this->loadPayments();
    }

    public function nextPaymentsPage(): void
    {
        $this->paymentsPage = min($this->paymentsLastPage, $this->paymentsPage + 1);
        $this->loadPayments();
    }

    public function loadAttendanceTab(): void
    {
        if (! $this->attendanceTabLoaded) {
            $this->attendanceTabLoaded = true;
            $this->cachedProfileSummary = null;
        }

        $this->refreshAttendanceTab();
    }

    public function updatedAttendanceMonth(): void
    {
        $this->attendancePage = 1;
        if ($this->attendanceTabLoaded) {
            $this->refreshAttendanceTab();
        }
    }

    public function previousAttendancePage(): void
    {
        $this->attendancePage = max(1, $this->attendancePage - 1);
        $this->refreshAttendanceTab();
    }

    public function nextAttendancePage(): void
    {
        $this->attendancePage = min($this->attendanceLastPage, $this->attendancePage + 1);
        $this->refreshAttendanceTab();
    }

    protected function refreshAttendanceTab(): void
    {
        $this->record->loadMissing(['activeBatchStudent.batch.trainer']);
        $this->activeBatch = $this->record->activeBatchStudent?->batch;

        if (! $this->activeBatch) {
            $this->attendanceRecords = new Collection;
            $this->attendancePercentage = null;
            $this->attendanceSummary = null;
            $this->attendanceTotal = 0;
            $this->attendanceLastPage = 1;

            return;
        }

        if (! filled($this->attendanceMonth) || ! preg_match('/^\d{4}-\d{2}$/', $this->attendanceMonth)) {
            $this->attendanceMonth = now()->format('Y-m');
        }

        $monthStart = \Illuminate\Support\Carbon::createFromFormat('Y-m', $this->attendanceMonth)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $query = Attendance::query()
            ->where('batch_id', $this->activeBatch->id)
            ->where('student_id', $this->record->id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        $this->attendanceTotal = (clone $query)->count();
        $perPage = CrmPagination::PER_PAGE;
        $this->attendanceLastPage = max(1, (int) ceil($this->attendanceTotal / $perPage));
        $this->attendancePage = min(max(1, $this->attendancePage), $this->attendanceLastPage);

        $this->attendanceRecords = $query
            ->orderByDesc('attendance_date')
            ->forPage($this->attendancePage, $perPage)
            ->get();

        $this->attendanceSummary = app(AttendanceService::class)
            ->summaryForStudentInMonth($this->record, $this->attendanceMonth);
        $this->attendancePercentage = $this->attendanceSummary['percentage'] ?? null;
    }

    public function downloadAttendanceMonthPdf(ReportService $reports, ReportPdfService $pdf): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($this->userCan(CrmPermission::StudentsView), 403);

        if (! filled($this->attendanceMonth) || ! preg_match('/^\d{4}-\d{2}$/', $this->attendanceMonth)) {
            $this->attendanceMonth = now()->format('Y-m');
        }

        $from = \Illuminate\Support\Carbon::createFromFormat('Y-m', $this->attendanceMonth)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        if ($to->isFuture()) {
            $to = now();
        }

        $data = $reports->generate(ReportType::AttendanceByStudent, [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'student_id' => $this->record->id,
        ]);

        $summary = app(AttendanceService::class)->summaryForStudentInMonth($this->record, $this->attendanceMonth);
        if ($summary) {
            $data['title'] .= ' · '.$summary['percentage'].'% ('.$summary['credited_days'].'/'.$summary['expected_days'].' working days)';
        }

        $filename = 'attendance-'.$this->record->id.'-'.$this->attendanceMonth.'.pdf';

        return response()->streamDownload(
            fn () => print $pdf->generate($data),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveEditedStudentProfile(
        array $data,
        StudentUpdateService $studentUpdates,
        EnrollmentPlacementService $placement,
        EnrollmentRollNumberService $rollNumbers,
        DocumentService $documents,
        IdCardService $idCards,
    ): void {
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
        $courseId = isset($data['course_id']) ? (int) $data['course_id'] : null;
        $batchId = filled($data['batch_id'] ?? null) ? (int) $data['batch_id'] : null;
        $currentBatchId = $this->record->activeBatchStudent?->batch_id;
        unset($data['enrollment_number'], $data['course_id'], $data['batch_id']);

        $this->record = $studentUpdates->update($this->record, $data, Auth::user());

        $enrollment = $this->record->activeEnrollment;
        $rollNumberChanged = false;
        $idCardRegenerated = false;
        $placementChanged = false;

        if ($enrollment && $courseId && $courseId !== (int) $enrollment->course_id) {
            $placement->updateCourse($enrollment, $courseId, Auth::user());
            $placementChanged = true;
            $enrollment = $this->record->fresh()->activeEnrollment;
        }

        if ($enrollment && $batchId && $batchId !== (int) $currentBatchId) {
            $placement->updateBatch($this->record->fresh(), $batchId, Auth::user());
            $placementChanged = true;
        }

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

        if ($photoUpdated || $documentsUpdated || $rollNumberChanged || $placementChanged) {
            $this->refreshRecord();
        }

        if ($placementChanged) {
            $this->feesTabLoaded = false;
            $this->attendanceTabLoaded = false;
        }

        $body = match (true) {
            $idCardRegenerated && $rollNumberChanged => 'Profile, documents, and roll number saved. ID card regenerated with the new photo.',
            $idCardRegenerated => 'Profile and documents saved. ID card regenerated with the new photo.',
            $rollNumberChanged && $photoUpdated => 'Profile, photo, and roll number saved successfully.',
            $rollNumberChanged => 'Profile and roll number saved successfully.',
            $placementChanged && $photoUpdated => 'Profile, class/batch, and photo saved successfully.',
            $placementChanged => 'Profile and class/batch saved successfully.',
            $photoUpdated => 'Photo updated. Turn on “Regenerate ID card” to refresh the PDF, or use Regenerate on the profile.',
            $documentsUpdated => 'Documents updated successfully.',
            default => 'Profile details saved successfully.',
        };

        Notification::make()
            ->title('Student updated')
            ->body($body)
            ->success()
            ->send();
    }

    protected function refreshRecord(): void
    {
        $this->record->refresh()->load([
            'enquiries.course',
            'activeEnrollment.course',
            'activeEnrollment.feeStructure',
            'activeEnrollment.admission.enquiry',
            'activeBatchStudent.batch.trainer',
        ]);

        $this->cachedProfileSummary = null;
    }

    protected function syncCatalogCourseFeeIfNeeded(): bool
    {
        if (! $this->userCan(CrmPermission::FeesAdjustStructure)) {
            return false;
        }

        $enrollment = $this->record->activeEnrollment;

        if (! $enrollment?->feeStructure || ! $enrollment->course) {
            return false;
        }

        $synced = app(CourseFeeSyncService::class)->syncToCatalogIfNeeded(
            $enrollment->feeStructure,
            $enrollment->course,
            Auth::user(),
        );

        if (! $synced) {
            return false;
        }

        $this->record->load(array_merge([
            'activeEnrollment.course',
            'activeEnrollment.feeStructure.installments',
        ], $this->miscChargesEagerLoadPaths()));
        $this->cachedProfileSummary = null;

        return true;
    }

    protected function resolveVisitEnquiry(array $data): ?Enquiry
    {
        if (isset($data['enquiry_id'])) {
            return Enquiry::query()->find($data['enquiry_id']);
        }

        return $this->record->enquiries->first()
            ?? $this->record->activeEnrollment?->admission?->enquiry;
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
                ->modalHeading('Add Visit')
                ->form(fn (): array => EnquiryFormSchema::addVisitFormFields(
                    $this->record->enquiries,
                    $this->record->activeEnrollment !== null,
                ))
                ->action(function (array $data): void {
                    $enquiry = $this->resolveVisitEnquiry($data);

                    if (! $enquiry) {
                        Notification::make()
                            ->title('No enquiry found')
                            ->body('Add an enquiry for this student before logging a visit.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        app(VisitMeetingAssignmentService::class)->assignFromFormData(
                            $this->record,
                            $enquiry,
                            Auth::user(),
                            $data,
                        );
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        Notification::make()
                            ->title('Could not add visit')
                            ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->refreshRecord();

                    Notification::make()
                        ->title('Visit added')
                        ->body('Staff assigned with your handoff notes.')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->licensed(LicenseFeature::Enquiries)
                    && $this->userCan(CrmPermission::LeadsCall)
                    && ($this->record->enquiries->isNotEmpty() || $this->record->activeEnrollment !== null)),
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
                ->before(function (array $data, Action $action): void {
                    $courseId = (int) ($data['course_id'] ?? 0);
                    $course = \App\Models\Course::query()->find($courseId);

                    if (! $course || (float) $course->fee <= 0) {
                        Notification::make()
                            ->title('Course fee not set')
                            ->body('Update the fee for this course in Classes admin before converting.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                })
                ->modalSubmitAction(function (Action $action): Action {
                    return $action->disabled(function (): bool {
                        $mounted = $this->mountedActionsData[0] ?? [];

                        if (! is_array($mounted)) {
                            return true;
                        }

                        $courseId = (int) ($mounted['course_id'] ?? 0);
                        $course = \App\Models\Course::query()->find($courseId);

                        return ! $course || (float) $course->fee <= 0;
                    });
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
                        ],
                    );

                    $this->refreshRecord();
                    $this->profileTab = 'documents';
                    $this->admissionTabLoaded = false;
                    $this->documentsTabLoaded = false;
                    $this->loadDocumentsTab();

                    Notification::make()
                        ->title('Converted to admission')
                        ->body("Admission {$admission->admission_number} for {$admission->enquiry?->course?->name}. Set fees via Adjust Fees after approval.")
                        ->success()
                        ->send();
                })
                ->visible(fn (ConvertToAdmissionPresenter $presenter): bool => $this->licensed(LicenseFeature::Admissions)
                    && $this->userCan(CrmPermission::AdmissionsView)
                    && $presenter->convertibleEnquiries($this->record)->isNotEmpty()),
            Action::make('setAdmissionFeePlan')
                ->label('Set fee plan')
                ->icon('heroicon-o-banknotes')
                ->button()
                ->color('warning')
                ->outlined()
                ->modalHeading('Admission fee plan')
                ->modalDescription('Set discount, misc charges, and installments before approval. Amounts must total the net fee.')
                ->modalWidth('2xl')
                ->fillForm(function (): array {
                    $this->loadAdmissionTab();
                    $admission = $this->activeAdmission ?? $this->record->admissions()
                        ->whereDoesntHave('enrollment')
                        ->with(['enquiry.course', 'miscFees', 'installmentPlans'])
                        ->latest()
                        ->first();

                    if (! $admission) {
                        return [];
                    }

                    return AdmissionFeePlanFormSchema::initialStateForAdmission($admission);
                })
                ->form(function (): array {
                    $this->loadAdmissionTab();
                    $admission = $this->activeAdmission ?? $this->record->admissions()
                        ->whereDoesntHave('enrollment')
                        ->with(['enquiry.course', 'miscFees', 'installmentPlans'])
                        ->latest()
                        ->first();

                    if (! $admission) {
                        return [];
                    }

                    return AdmissionFeePlanFormSchema::fieldsForAdmission($admission);
                })
                ->extraModalFooterActions([
                    Action::make('fillAdmissionInstallmentBalance')
                        ->label('Fill balance on last row')
                        ->color('gray')
                        ->action(function (Action $action): void {
                            $livewire = $action->getLivewire();
                            $mounted = $livewire->mountedActionsData[0] ?? [];

                            if (! is_array($mounted)) {
                                return;
                            }

                            $net = AdmissionFeePlanFormSchema::resolveNetFeeFromArray($mounted);
                            $plan = $mounted['installment_plan'] ?? [];

                            if ($plan === [] || $net <= 0) {
                                return;
                            }

                            $livewire->mountedActionsData[0]['installment_plan'] = FeePlanCalculator::fillBalanceOnLastRow($plan, $net);
                            $livewire->mountedActionsData[0]['use_installment_plan'] = true;
                        }),
                ])
                ->modalSubmitAction(function (Action $action): Action {
                    return $action
                        ->label('Save fee plan')
                        ->disabled(function (): bool {
                            $mounted = $this->mountedActionsData[0] ?? [];

                            if (! is_array($mounted)) {
                                return true;
                            }

                            if ((float) ($this->activeAdmission?->course_fee ?? $this->record->admissions()->latest()->value('course_fee') ?? 0) <= 0) {
                                return true;
                            }

                            return ! FeePlanSubmissionGuard::canSubmitConvert($mounted);
                        });
                })
                ->before(function (array $data, Action $action): void {
                    FeePlanSubmissionGuard::assertConvertable($data, $action);
                })
                ->action(function (array $data, AdmissionService $admissions): void {
                    abort_unless($this->canManageAdmissionFeePlan, 403);
                    $this->loadAdmissionTab();

                    if (! $this->activeAdmission) {
                        return;
                    }

                    $this->activeAdmission = $admissions->updateFeePlan($this->activeAdmission, $data, Auth::user());
                    $this->admissionTabLoaded = false;
                    $this->loadAdmissionTab();

                    Notification::make()
                        ->title('Fee plan saved')
                        ->body('Net fee is now ₹'.$this->admissionNetFee.'.')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->canManageAdmissionFeePlan),
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
            Action::make('payMiscCharge')
                ->label('Pay misc')
                ->icon('heroicon-o-banknotes')
                ->modalHeading('Pay miscellaneous charge')
                ->modalWidth('lg')
                ->arguments(['chargeId' => 0])
                ->fillForm(fn (array $arguments): array => ['fee_misc_charge_id' => (int) ($arguments['chargeId'] ?? 0)])
                ->form(function (array $arguments): array {
                    $charge = FeeMiscCharge::query()->find((int) ($arguments['chargeId'] ?? 0));

                    if (! $charge) {
                        return [];
                    }

                    return PayMiscChargeFormSchema::fields($charge);
                })
                ->action(function (array $data, array $arguments, PaymentService $payments): void {
                    abort_unless($this->userCan(CrmPermission::FeesCollect), 403);

                    $feeStructure = $this->record->activeEnrollment?->feeStructure;
                    $charge = app(FeeMiscChargeService::class)->resolveForStudent(
                        $this->record,
                        (int) ($arguments['chargeId'] ?? $data['fee_misc_charge_id'] ?? 0),
                    );

                    if (! $feeStructure) {
                        return;
                    }

                    $proof = $data['proof_image'] ?? null;

                    if (is_array($proof)) {
                        $proof = $proof[0] ?? null;
                    }

                    $payment = $payments->addMisc(
                        $feeStructure,
                        $this->record,
                        $charge,
                        $data,
                        (string) $proof,
                        Auth::user(),
                    );

                    app(StorageCleanupService::class)->pruneLivewireTempFiles(0);
                    $this->feesTabLoaded = false;
                    $this->receiptsTabLoaded = false;
                    $this->loadFeesTab();
                    $this->refreshRecord();

                    Notification::make()
                        ->title('Misc charge paid')
                        ->body("Receipt {$payment->receipt_number} · ₹".number_format((float) $payment->amount, 0))
                        ->success()
                        ->send();
                })
                ->visible(false),
            Action::make('addPayment')
                ->label('Add Payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->button()
                ->modalHeading('Add payment')
                ->modalWidth('lg')
                ->arguments(['miscChargeId' => null])
                ->form(function (array $arguments): array {
                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return [];
                    }

                    $miscChargeId = filled($arguments['miscChargeId'] ?? null)
                        ? (int) $arguments['miscChargeId']
                        : null;

                    return AddPaymentFormSchema::fields($feeStructure, $miscChargeId);
                })
                ->action(function (array $data, array $arguments, PaymentService $payments): void {
                    abort_unless($this->userCan(CrmPermission::FeesCollect), 403);

                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return;
                    }

                    $proof = $data['proof_image'] ?? null;

                    if (is_array($proof)) {
                        $proof = $proof[0] ?? null;
                    }

                    $payment = ($data['payment_target'] ?? 'tuition') === 'misc'
                        ? $payments->addMisc(
                            $feeStructure,
                            $this->record,
                            app(FeeMiscChargeService::class)->resolveForStudent(
                                $this->record,
                                (int) ($data['fee_misc_charge_id'] ?? $arguments['miscChargeId'] ?? 0),
                            ),
                            $data,
                            (string) $proof,
                            Auth::user(),
                        )
                        : $payments->add(
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

                    $body = "Receipt {$payment->receipt_number} · ₹".number_format((float) $payment->amount, 0).' · PDF generated';
                    $isMiscPayment = ($data['payment_target'] ?? 'tuition') === 'misc';

                    if ($payment->shortfallSummary()) {
                        $body .= ' · '.$payment->shortfallSummary();
                    }

                    if (! $isMiscPayment && Payment::query()->where('fee_structure_id', $feeStructure->id)->whereNull('fee_misc_charge_id')->count() === 1) {
                        $body .= ' · ID card generated';
                    }

                    Notification::make()
                        ->title($isMiscPayment ? 'Misc payment recorded' : 'Payment recorded')
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
                ->modalDescription('Change discount, add misc charges, or reschedule installments. Reason is only needed when discount changes.')
                ->modalWidth('2xl')
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
                ->action(function (array $data, FeeStructureService $fees, FeeMiscChargeService $miscCharges): void {
                    abort_unless($this->userCan(CrmPermission::FeesAdjustStructure), 403);

                    $feeStructure = $this->record->activeEnrollment?->feeStructure;

                    if (! $feeStructure) {
                        return;
                    }

                    $newMiscCharges = AdjustFeeStructureFormSchema::resolveNewMiscCharges($data);

                    $fees->updateByAdmin(
                        $feeStructure,
                        AdjustFeeStructureFormSchema::resolveForSave($feeStructure, $data),
                        Auth::user(),
                    );

                    foreach ($newMiscCharges as $row) {
                        $miscCharges->addSeparateCharge(
                            $feeStructure,
                            $row['label'],
                            $row['amount'],
                            $row['due_date'],
                            Auth::user(),
                        );
                    }

                    $this->refreshRecord();
                    $this->feesTabLoaded = false;
                    $this->loadFeesTab();

                    $body = 'Fee structure saved.';

                    if ($newMiscCharges !== []) {
                        $body = count($newMiscCharges) === 1
                            ? '1 misc charge added. Student can pay it via Add Payment.'
                            : count($newMiscCharges).' misc charges added. Student can pay them via Add Payment.';
                    }

                    Notification::make()
                        ->title('Fees updated')
                        ->body($body)
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
                ->modalWidth('2xl')
                ->form(function (): array {
                    $admission = $this->resolveAdmissionForDocuments();

                    return StudentProfileFormSchema::forEdit(
                        $this->record->activeEnrollment !== null,
                        $this->record->id,
                        $admission,
                        $this->record->activeEnrollment?->hasIdCard() ?? false,
                        $this->record->activeEnrollment,
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
                    'course_id' => $this->record->activeEnrollment?->course_id,
                    'batch_id' => $this->record->activeBatchStudent?->batch_id,
                    'enrollment_number' => $this->record->activeEnrollment?->enrollment_number,
                    'custom_data' => $this->record->custom_data ?? [],
                ])
                ->action(function (
                    array $data,
                    StudentUpdateService $studentUpdates,
                    EnrollmentPlacementService $placement,
                    EnrollmentRollNumberService $rollNumbers,
                    DocumentService $documents,
                    IdCardService $idCards,
                ): void {
                    try {
                        $this->saveEditedStudentProfile(
                            $data,
                            $studentUpdates,
                            $placement,
                            $rollNumbers,
                            $documents,
                            $idCards,
                        );
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        Notification::make()
                            ->title('Could not save profile')
                            ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                            ->danger()
                            ->send();
                    } catch (\Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Could not save profile')
                            ->body('Something went wrong while saving. Please try again.')
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => $this->userCan(CrmPermission::StudentsEdit)),
        ];
    }

    protected function pendingCallMatchesStudent(int $studentId): bool
    {
        return (int) $this->record->id === $studentId;
    }

    protected function studentForCloseMeeting(): Student
    {
        return $this->record;
    }

    protected function afterMeetingClosed(): void
    {
        $this->refreshRecord();
        $this->visitsTabLoaded = false;
        $this->casesTabLoaded = false;
        $this->cachedProfileSummary = null;
        $this->loadVisitsTab();
        $this->loadCasesTab();
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
        $this->casesTabLoaded = false;
        $this->cachedProfileSummary = null;
        $this->loadCallsTab();
        $this->loadVisitsTab();
        $this->loadCasesTab();
        $this->profileTab = $this->logCallStudentCaseId ? 'cases' : 'calls';

        Notification::make()
            ->title('Call logged')
            ->body('Call saved under your name in the Calls tab.')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.close-meeting-modal')
                ->viewData(fn (): array => [
                    'showCloseMeetingModal' => $this->showCloseMeetingModal,
                    'isEnrolledStudent' => $this->record->activeEnrollment !== null,
                    'campusOutcomeOptions' => $this->closeMeetingCampusOutcomeOptions(),
                    'visitStatusOptions' => $this->closeMeetingVisitStatusOptions(),
                    'callingStaffOptions' => $this->closeMeetingCallingStaffOptions(),
                    'closeMeetingStatus' => $this->closeMeetingStatus,
                    'closeMeetingCallingMode' => $this->closeMeetingCallingMode,
                    'closeMeetingResolutionMode' => $this->closeMeetingResolutionMode,
                    'caseTypeOptions' => $this->closeMeetingCaseTypeOptions(),
                ]),
            View::make('filament.pages.partials.log-call-modal')
                ->viewData(fn (): array => [
                    'showLogCallModal' => $this->showLogCallModal,
                    'logCallForm' => $this->logCallForm,
                    'logCallModalMode' => $this->logCallStudentCaseId ? 'case' : 'profile',
                    'logCallLeadName' => $this->record->name,
                    'logCallLeadPhone' => $this->record->mobile,
                    'logCallCaseNumber' => $this->logCallStudentCaseId
                        ? StudentCase::query()->find($this->logCallStudentCaseId)?->case_number
                        : null,
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
                                    'leadTimeline' => $this->leadTimeline,
                                    'record' => $this->record,
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
                    'cases' => Tab::make('Cases')
                        ->icon('heroicon-o-briefcase')
                        ->badge(fn (): ?string => $this->record->activeEnrollment && $this->userCan(CrmPermission::CasesView)
                            ? (string) app(StudentCaseService::class)->openCountForStudent($this->record) ?: null
                            : null)
                        ->visible(fn (): bool => $this->record->activeEnrollment !== null
                            && $this->userCan(CrmPermission::CasesView))
                        ->schema([
                            View::make('filament.pages.partials.student-profile-cases')
                                ->viewData(fn (): array => [
                                    'casesTabLoaded' => $this->casesTabLoaded,
                                    'cases' => $this->cases,
                                    'expandedCaseId' => $this->expandedCaseId,
                                    'caseTransferNote' => $this->caseTransferNote,
                                    'caseTransferAssigneeId' => $this->caseTransferAssigneeId,
                                    'caseClosingNote' => $this->caseClosingNote,
                                    'staffOptions' => StudentCaseService::activeStaffOptions(),
                                    'caseService' => app(StudentCaseService::class),
                                    'viewer' => Auth::user(),
                                    'canOpenCaseAsAdmin' => app(StudentCaseService::class)->canOpenAsAdmin(Auth::user(), $this->record),
                                    'showOpenCaseForm' => $this->showOpenCaseForm,
                                    'openCaseType' => $this->openCaseType,
                                    'openCaseTitle' => $this->openCaseTitle,
                                    'openCaseSummary' => $this->openCaseSummary,
                                    'openCaseAssigneeId' => $this->openCaseAssigneeId,
                                    'openCaseHandoffNote' => $this->openCaseHandoffNote,
                                    'caseTypeOptions' => CampusVisitPurpose::options(),
                                ]),
                        ]),
                    'messages' => Tab::make('Messages')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->visible(fn (): bool => $this->licensed(LicenseFeature::WhatsApp))
                        ->schema([
                            View::make('filament.pages.partials.student-profile-messages')
                                ->viewData(fn (): array => $this->whatsAppMessagesViewData()),
                        ]),
                    'documents' => Tab::make('Documents')
                        ->icon('heroicon-o-folder')
                        ->schema([
                            View::make('filament.pages.partials.student-profile-documents')
                                ->viewData(fn (): array => [
                                    'showAdmissionSection' => $this->licensed(LicenseFeature::Admissions),
                                    'admissionTabLoaded' => $this->admissionTabLoaded,
                                    'activeAdmission' => $this->activeAdmission,
                                    'record' => $this->record,
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
                                    'record' => $this->record->loadMissing(array_merge([
                                        'activeEnrollment.course',
                                        'activeEnrollment.feeStructure.installments',
                                    ], $this->miscChargesEagerLoadPaths())),
                                    'feesTabLoaded' => $this->feesTabLoaded,
                                    'payments' => $this->payments,
                                    'paymentsMonth' => $this->paymentsMonth,
                                    'paymentsPage' => $this->paymentsPage,
                                    'paymentsTotal' => $this->paymentsTotal,
                                    'paymentsLastPage' => $this->paymentsLastPage,
                                    'paymentsPerPage' => CrmPagination::PER_PAGE,
                                    'installments' => $this->installments,
                                    'penalties' => $this->penalties,
                                    'feeStructureHistory' => $this->feeStructureHistory,
                                    'canCollectFees' => $this->userCan(CrmPermission::FeesCollect),
                                    'canRequestMiscAdjustment' => $this->userCanRequestMiscAdjustment(),
                                    'adjustmentsUrl' => MiscChargeAdjustmentRequestsPage::getUrl(),
                                    'canReviewMiscAdjustments' => $this->userIsSuperAdmin(),
                                    'discountSummary' => app(FeeDiscountHistoryService::class)->studentSummary($this->record),
                                    'discountTimeline' => app(FeeDiscountHistoryService::class)->studentTimeline($this->record),
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
                                    'attendanceSummary' => $this->attendanceSummary,
                                    'attendanceMonth' => $this->attendanceMonth,
                                    'attendancePage' => $this->attendancePage,
                                    'attendanceTotal' => $this->attendanceTotal,
                                    'attendanceLastPage' => $this->attendanceLastPage,
                                    'attendancePerPage' => CrmPagination::PER_PAGE,
                                    'student' => $this->record,
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
                    'activities' => Tab::make('Exams')
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
