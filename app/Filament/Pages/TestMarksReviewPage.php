<?php

namespace App\Filament\Pages;

use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Models\StudentMarksheet;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityMarksWhatsAppService;
use App\Services\ExamWindowService;
use App\Services\ResultDeclarationService;
use App\Support\CrmHint;
use App\Support\ExamTestGroupMatrix;
use App\Support\PublishedResultsGate;
use App\Support\ResultAuditTrail;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class TestMarksReviewPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Test mark sheet';

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
    }

    public function getTitle(): string
    {
        return $this->markSheet['test_label'] ?? static::$title;
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('activity.marks.review');
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (is_array($this->markSheet) && ! $this->marksAreLocked()) {
            $actions[] = Action::make('uploadMarks')
                ->label('Upload marks')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->url(fn (): string => BulkActivityMarksImportPage::urlForTest(
                    (string) ($this->markSheet['test_label'] ?? ''),
                    isset($this->markSheet['activity_type_id']) ? (int) $this->markSheet['activity_type_id'] : null,
                    isset($this->markSheet['batch_id']) ? (int) $this->markSheet['batch_id'] : null,
                    $this->markSheet['date']?->format('Y-m-d') ?? null,
                ));
        }

        $actions[] = Action::make('back')
            ->label('Back to tests')
            ->url(\App\Filament\Resources\ActivitySessions\ActivitySessionResource::getUrl('index'));

        return $actions;
    }

    public function publishResults(ResultDeclarationService $declarations): void
    {
        abort_unless(FeatureGate::enabled(LicenseFeature::Results), 403);
        abort_unless(CrmAccess::can(Auth::user(), CrmPermission::MarksImport), 403);

        $this->validate([
            'declarationDate' => 'required|date',
        ]);

        if (blank($this->groupKey)) {
            Notification::make()->title('Test not found')->warning()->send();

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
        abort_unless(Auth::user()?->hasRole(RoleName::SuperAdmin->value), 403);

        $this->validate([
            'marksheetIssueDate' => 'required|date',
        ]);

        if (blank($this->groupKey)) {
            Notification::make()->title('Test not found')->warning()->send();

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
        abort_unless(Auth::user()?->hasRole(RoleName::SuperAdmin->value), 403);

        $this->validate([
            'marksheetIssueDate' => 'required|date',
        ]);

        if (blank($this->groupKey)) {
            Notification::make()->title('Test not found')->warning()->send();

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
        abort_unless(Auth::user()?->hasRole(RoleName::SuperAdmin->value), 403);

        if (blank($this->groupKey)) {
            Notification::make()->title('Test not found')->warning()->send();

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
        abort_unless(Auth::user()?->hasRole(RoleName::SuperAdmin->value), 403);

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
        abort_unless(Auth::user()?->hasRole(RoleName::SuperAdmin->value), 403);

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
        abort_unless(CrmAccess::can(Auth::user(), CrmPermission::WhatsappCampaigns), 403);

        $this->validate([
            'whatsappTemplateId' => 'required|exists:whatsapp_templates,id',
        ]);

        if (! is_array($this->markSheet) || blank($this->groupKey)) {
            Notification::make()
                ->title('Test not found')
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
                $this->markSheet['date']?->format('Y-m-d') ?? now()->toDateString(),
            );

            Notification::make()
                ->title('WhatsApp campaign queued')
                ->body("{$campaign->total_recipients} student(s) will receive marks for {$this->markSheet['test_label']}.")
                ->success()
                ->duration(10000)
                ->send();
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
                    'canSendWhatsApp' => FeatureGate::enabled(LicenseFeature::WhatsApp)
                        && CrmAccess::can(Auth::user(), CrmPermission::WhatsappCampaigns),
                    'resultStatus' => $this->resultStatus(),
                    'canPublish' => FeatureGate::enabled(LicenseFeature::Results)
                        && CrmAccess::can(Auth::user(), CrmPermission::MarksImport)
                        && $this->examWindowAllowsPublish(),
                    'examWindowStatus' => $this->examWindowStatus(),
                    'canIssueMarksheet' => FeatureGate::enabled(LicenseFeature::Marksheets)
                        && (Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false),
                    'canManagePublish' => Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false,
                    'marksAreLocked' => $this->marksAreLocked(),
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
