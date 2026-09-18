<?php

namespace App\Filament\Pages;

use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Models\StudentMarksheet;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityMarksWhatsAppService;
use App\Services\ExamTestGroupService;
use App\Services\ExamWindowService;
use App\Services\ResultDeclarationService;
use App\Services\StudentExamMarksWriter;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\ExamTestGroupMatrix;
use App\Support\PublishedResultsGate;
use App\Support\ResultAuditTrail;
use App\Support\WhatsAppSendUi;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TestMarksReviewPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Exam mark sheet';

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Marks)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::MarksImport);
    }

    public ?string $groupKey = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $markSheet = null;

    public ?int $whatsappTemplateId = null;

    public ?string $declarationDate = null;

    public ?string $marksheetIssueDate = null;

    public ?string $principalRemarks = null;

    public bool $editingMarks = false;

    /**
     * @var array<int|string, array<string, string>>
     */
    public array $marksDraft = [];

    public function mount(): void
    {
        $this->declarationDate = now()->toDateString();
        $this->marksheetIssueDate = now()->toDateString();

        $this->groupKey = request()->query('group');

        if (filled($this->groupKey)) {
            $this->markSheet = ExamTestGroupMatrix::markSheetForGroup($this->groupKey);
            $declaration = app(ResultDeclarationService::class)->findForGroupKey((string) $this->groupKey);
            $this->principalRemarks = $declaration?->remarks;
        }

        $this->whatsappTemplateId = app(ActivityMarksWhatsAppService::class)->defaultTemplate()?->id;
    }

    public function getTitle(): string
    {
        if (! is_array($this->markSheet)) {
            return static::$title;
        }

        return filled($this->markSheet['test_label'] ?? null)
            ? (string) $this->markSheet['test_label']
            : static::$title;
    }

    public function getSubheading(): ?string
    {
        if ($this->userCanBulkEditMarks()) {
            return $this->marksAreLocked()
                ? 'Marks are locked. Unlock above before editing this grid.'
                : 'Edit marks on this grid. Empty cell = Absent. Update Excel is for a full class file.';
        }

        return CrmHint::text('activity.marks.review');
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (filled($this->groupKey) && is_array($this->markSheet)) {
            $actions[] = Action::make('renameExam')
                ->label('Rename')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->form([
                    TextInput::make('name')
                        ->label('Exam name')
                        ->required()
                        ->maxLength(255),
                ])
                ->fillForm(fn (): array => [
                    'name' => (string) ($this->markSheet['test_label'] ?? ''),
                ])
                ->action(function (array $data): void {
                    abort_unless(Auth::user() && app(ExamTestGroupService::class)->userCanManage(Auth::user()), 403);

                    try {
                        app(ExamTestGroupService::class)->renameGroup(
                            Auth::user(),
                            (string) $this->groupKey,
                            (string) ($data['name'] ?? ''),
                        );
                        $this->markSheet = ExamTestGroupMatrix::markSheetForGroup((string) $this->groupKey);
                        Notification::make()
                            ->title('Exam renamed')
                            ->body('Marks were not changed.')
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Name not saved')
                            ->body(collect($exception->errors())->flatten()->first() ?: 'Could not rename this exam.')
                            ->danger()
                            ->send();
                    }
                });
        }

        if ($this->userCanBulkEditMarks() && is_array($this->markSheet) && ! $this->marksAreLocked() && ! $this->editingMarks) {
            $actions[] = Action::make('editMarks')
                ->label('Edit marks')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('primary')
                ->action(function () {
                    $this->startBulkEdit();
                });
        }

        if (is_array($this->markSheet) && ! $this->marksAreLocked()) {
            $actions[] = Action::make('uploadMarks')
                ->label('Update Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->url(fn (): string => BulkActivityMarksImportPage::urlForTest(
                    (string) ($this->markSheet['test_label'] ?? ''),
                    isset($this->markSheet['activity_type_id']) ? (int) $this->markSheet['activity_type_id'] : null,
                    isset($this->markSheet['batch_id']) ? (int) $this->markSheet['batch_id'] : null,
                    $this->markSheetDateForUrl(),
                    $this->groupKey,
                ));
        }

        $actions[] = Action::make('back')
            ->label('Back to '.CrmMenuLabels::examResults())
            ->url(\App\Filament\Resources\ActivitySessions\ActivitySessionResource::getUrl('index'));

        return $actions;
    }

    public function userCanBulkEditMarks(): bool
    {
        $user = Auth::user();

        if (! $user?->is_active) {
            return false;
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        if (in_array(StaffJobRole::AcademicCoordinator->value, CrmAccess::jobRoleNamesFor($user), true)) {
            return true;
        }

        return CrmAccess::can($user, CrmPermission::AcademicsManage);
    }

    protected function markSheetDateForUrl(): ?string
    {
        return \App\Support\StudentExamMarksMatrix::dateForUrl(
            is_array($this->markSheet) ? ($this->markSheet['date'] ?? null) : null,
        );
    }

    public function startBulkEdit(): void
    {
        abort_unless($this->userCanBulkEditMarks(), 403);

        if (! is_array($this->markSheet) || blank($this->groupKey)) {
            Notification::make()->title('Exam not found')->warning()->send();

            return;
        }

        if ($this->marksAreLocked()) {
            Notification::make()
                ->title('Marks are locked')
                ->body('Unlock this exam before editing the grid.')
                ->warning()
                ->send();

            return;
        }

        $draft = [];

        foreach ($this->markSheet['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $studentId = (int) ($row['student_id'] ?? 0);

            if ($studentId < 1) {
                continue;
            }

            foreach ($this->markSheet['subjects'] ?? [] as $subject) {
                $cell = \App\Support\StudentExamMarksMatrix::sheetCell($row, (string) $subject);
                $draft[$studentId][(string) $subject] = $cell['marks'] === null ? '' : (string) $cell['marks'];
            }
        }

        $this->marksDraft = $draft;
        $this->editingMarks = true;
    }

    public function cancelBulkEdit(): void
    {
        $this->editingMarks = false;
        $this->marksDraft = [];
    }

    public function saveBulkMarks(StudentExamMarksWriter $writer): void
    {
        abort_unless($this->userCanBulkEditMarks(), 403);

        if (! $this->editingMarks || blank($this->groupKey)) {
            Notification::make()->title('Nothing to save')->warning()->send();

            return;
        }

        if ($this->marksAreLocked()) {
            Notification::make()
                ->title('Marks are locked')
                ->body('Unlock this exam before editing the grid.')
                ->warning()
                ->send();

            return;
        }

        try {
            $saved = $writer->saveForGroup(
                (string) $this->groupKey,
                $this->marksDraft,
                Auth::user(),
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not save marks')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Check the scores and try again.')
                ->danger()
                ->send();

            return;
        }

        $this->markSheet = ExamTestGroupMatrix::markSheetForGroup((string) $this->groupKey);
        $this->cancelBulkEdit();

        Notification::make()
            ->title($saved > 0 ? 'Marks saved' : 'No scores changed')
            ->body('This class grid was updated. Teachers still enter their own papers from Subject progress.')
            ->success()
            ->send();
    }

    public function publishResults(ResultDeclarationService $declarations): void
    {
        abort_unless(FeatureGate::enabled(LicenseFeature::Results), 403);
        abort_unless(CrmAccess::can(Auth::user(), CrmPermission::MarksPublish), 403);

        $this->validate([
            'declarationDate' => 'required|date',
        ]);

        if (blank($this->groupKey)) {
            Notification::make()->title('Exam not found')->warning()->send();

            return;
        }

        try {
            $declaration = $declarations->publish(
                (string) $this->groupKey,
                Auth::user(),
                $this->declarationDate,
            );

            Notification::make()
                ->title('Results published online')
                ->body("{$declaration->studentMarksheets()->count()} student(s) can now see marks in the student portal.")
                ->success()
                ->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not publish')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    public function issueMarksheets(ResultDeclarationService $declarations): void
    {
        abort_unless(FeatureGate::enabled(LicenseFeature::Marksheets), 403);
        abort_unless(CrmAccess::can(Auth::user(), CrmPermission::MarksPublish), 403);

        $this->validate([
            'marksheetIssueDate' => 'required|date',
        ]);

        if (blank($this->groupKey)) {
            Notification::make()->title('Exam not found')->warning()->send();

            return;
        }

        try {
            $declaration = $declarations->issueMarksheets(
                (string) $this->groupKey,
                Auth::user(),
                $this->marksheetIssueDate,
            );

            Notification::make()
                ->title('Marksheets generated')
                ->body("PDF marksheets created for {$declaration->studentMarksheets()->count()} student(s).")
                ->success()
                ->duration(10000)
                ->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not issue marksheets')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Could not issue marksheets')
                ->body($exception->getMessage())
                ->danger()
                ->duration(15000)
                ->send();
        }
    }

    public function regenerateMarksheets(ResultDeclarationService $declarations): void
    {
        abort_unless(FeatureGate::enabled(LicenseFeature::Marksheets), 403);
        abort_unless(CrmAccess::can(Auth::user(), CrmPermission::MarksPublish), 403);

        $this->validate([
            'marksheetIssueDate' => 'required|date',
        ]);

        if (blank($this->groupKey)) {
            Notification::make()->title('Exam not found')->warning()->send();

            return;
        }

        try {
            $declaration = $declarations->regenerateMarksheets(
                (string) $this->groupKey,
                Auth::user(),
                $this->marksheetIssueDate,
            );

            Notification::make()
                ->title('Marksheets regenerated')
                ->body("PDF marksheets refreshed for {$declaration->studentMarksheets()->count()} student(s).")
                ->success()
                ->duration(10000)
                ->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not regenerate marksheets')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Could not regenerate marksheets')
                ->body($exception->getMessage())
                ->danger()
                ->duration(15000)
                ->send();
        }
    }

    public function savePrincipalRemarks(ResultDeclarationService $declarations): void
    {
        if (blank($this->groupKey)) {
            return;
        }

        $declarations->savePrincipalRemarks((string) $this->groupKey, $this->principalRemarks);

        Notification::make()
            ->title('Principal remarks saved')
            ->success()
            ->send();
    }

    public function unpublishResults(ResultDeclarationService $declarations): void
    {
        abort_unless(CrmAccess::can(Auth::user(), CrmPermission::MarksPublish), 403);

        if (blank($this->groupKey)) {
            Notification::make()->title('Exam not found')->warning()->send();

            return;
        }

        try {
            $declarations->unpublish((string) $this->groupKey, Auth::user());

            Notification::make()
                ->title('Results unpublished')
                ->body('Marks are hidden from the student portal. You can edit marks and publish again.')
                ->success()
                ->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not unpublish')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    public function lockMarks(ResultDeclarationService $declarations): void
    {
        abort_unless(CrmAccess::can(Auth::user(), CrmPermission::MarksPublish), 403);

        if (blank($this->groupKey)) {
            return;
        }

        try {
            $declarations->lockMarks((string) $this->groupKey, Auth::user());

            Notification::make()->title('Marks locked')->success()->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not lock marks')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    public function unlockMarks(ResultDeclarationService $declarations): void
    {
        abort_unless(CrmAccess::can(Auth::user(), CrmPermission::MarksPublish), 403);

        if (blank($this->groupKey)) {
            return;
        }

        try {
            $declarations->unlockMarks((string) $this->groupKey, Auth::user());

            Notification::make()
                ->title('Marks unlocked')
                ->body('Marks can be edited. Re-publish after corrections to refresh student portal snapshots.')
                ->warning()
                ->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not unlock marks')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    public function marksAreLocked(): bool
    {
        if (blank($this->groupKey)) {
            return false;
        }

        return PublishedResultsGate::marksAreLocked((string) $this->groupKey);
    }

    /**
     * @return array<int, \App\Models\AuditLog>
     */
    public function auditTrailEntries(): array
    {
        if (blank($this->groupKey)) {
            return [];
        }

        return ResultAuditTrail::entriesForGroupKey((string) $this->groupKey)->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function resultStatus(): array
    {
        if (blank($this->groupKey)) {
            return ResultDeclarationService::statusMetaForGroupKey('');
        }

        return ResultDeclarationService::statusMetaForGroupKey((string) $this->groupKey);
    }

    /**
     * @return array<int, StudentMarksheet>
     */
    public function studentMarksheetsByStudentId(): array
    {
        if (blank($this->groupKey)) {
            return [];
        }

        $declaration = app(ResultDeclarationService::class)->findForGroupKey((string) $this->groupKey);

        if (! $declaration) {
            return [];
        }

        return $declaration->studentMarksheets()
            ->get()
            ->keyBy('student_id')
            ->all();
    }

    public function queueWhatsAppCampaign(ActivityMarksWhatsAppService $marksWhatsApp): void
    {
        abort_unless(FeatureGate::enabled(LicenseFeature::WhatsApp), 403);
        abort_unless(CrmAccess::canSendExamMarksWhatsApp(Auth::user()), 403);

        $templateId = $marksWhatsApp->resolveTemplateId($this->whatsappTemplateId);

        if ($templateId === null) {
            Notification::make()
                ->title('Exam marks template not set')
                ->body('Pick test_marks on WhatsApp → Automations → Exam marks, then try again.')
                ->warning()
                ->send();

            return;
        }

        $this->whatsappTemplateId = $templateId;

        if (! is_array($this->markSheet) || blank($this->groupKey)) {
            Notification::make()
                ->title('Exam not found')
                ->body('Open a test mark sheet first, then send WhatsApp messages.')
                ->warning()
                ->send();

            return;
        }

        $template = WhatsAppTemplate::query()
            ->whereKey($this->whatsappTemplateId)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            Notification::make()
                ->title('Template unavailable')
                ->body('Choose an active pre-approved WhatsApp template.')
                ->danger()
                ->send();

            return;
        }

        try {
            $campaign = $marksWhatsApp->queueMarksCampaign(
                Auth::user(),
                $template->id,
                $this->groupKey,
                (string) $this->markSheet['test_label'],
                $this->markSheetDateForUrl() ?? now()->toDateString(),
            );

            Notification::make()
                ->title('WhatsApp queued')
                ->body("Opening send progress for {$campaign->total_recipients} student(s). Keep this tab open — do not click Send again.")
                ->success()
                ->duration(8000)
                ->send();

            if ($url = WhatsAppSendUi::campaignViewUrl($campaign->id)) {
                $this->redirect($url);
            }
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Could not queue WhatsApp')
                ->body($exception instanceof \InvalidArgumentException
                    ? $exception->getMessage()
                    : 'Check the template and that students have mobile numbers.')
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<int, string>
     */
    public function whatsappTemplateOptions(): array
    {
        return WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.test-marks-review')
                ->viewData(fn (): array => [
                    'markSheet' => $this->markSheet,
                    'groupKey' => $this->groupKey,
                    'whatsappTemplateOptions' => $this->whatsappTemplateOptions(),
                    'defaultMarksTemplateName' => app(ActivityMarksWhatsAppService::class)->defaultTemplateName(),
                    'examMarksAutomationsUrl' => ManageWhatsAppSettings::getUrl(['automation' => 'exam-marks']),
                    'canSendWhatsApp' => FeatureGate::enabled(LicenseFeature::WhatsApp)
                        && CrmAccess::canSendExamMarksWhatsApp(Auth::user()),
                    'resultStatus' => $this->resultStatus(),
                    'canPublish' => FeatureGate::enabled(LicenseFeature::Results)
                        && CrmAccess::can(Auth::user(), CrmPermission::MarksPublish)
                        && $this->examWindowAllowsPublish(),
                    'examWindowStatus' => $this->examWindowStatus(),
                    'canIssueMarksheet' => FeatureGate::enabled(LicenseFeature::Marksheets)
                        && CrmAccess::can(Auth::user(), CrmPermission::MarksPublish),
                    'canManagePublish' => CrmAccess::can(Auth::user(), CrmPermission::MarksPublish),
                    'marksAreLocked' => $this->marksAreLocked(),
                    'canBulkEditMarks' => $this->userCanBulkEditMarks(),
                    'editingMarks' => $this->editingMarks,
                    'auditTrailEntries' => $this->auditTrailEntries(),
                    'studentMarksheets' => $this->studentMarksheetsByStudentId(),
                ]),
        ]);
    }

    protected function examWindowAllowsPublish(): bool
    {
        if (blank($this->groupKey)) {
            return false;
        }

        $window = app(ExamWindowService::class)->findForGroupKey((string) $this->groupKey);

        if (! $window) {
            return true;
        }

        return $window->status === \App\Enums\ExamWindowStatus::Approved;
    }

    /**
     * @return array{exists: bool, label: ?string, url: ?string}
     */
    protected function examWindowStatus(): array
    {
        if (blank($this->groupKey)) {
            return ['exists' => false, 'label' => null, 'url' => null];
        }

        $window = app(ExamWindowService::class)->findForGroupKey((string) $this->groupKey);

        if (! $window) {
            return ['exists' => false, 'label' => null, 'url' => null];
        }

        return [
            'exists' => true,
            'label' => $window->status->label(),
            'url' => ExamWindowPage::getUrl(['window' => $window->id]),
        ];
    }
}
