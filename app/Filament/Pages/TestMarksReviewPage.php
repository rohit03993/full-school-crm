<?php

namespace App\Filament\Pages;

use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Models\StudentMarksheet;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityMarksWhatsAppService;
use App\Services\ResultDeclarationService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\ExamTestGroupMatrix;
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

    public function mount(): void
    {
        $this->declarationDate = now()->toDateString();
        $this->marksheetIssueDate = now()->toDateString();

        $this->groupKey = request()->query('group');

        if (filled($this->groupKey)) {
            $this->markSheet = ExamTestGroupMatrix::markSheetForGroup($this->groupKey);
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
        return [
            Action::make('uploadMarks')
                ->label('Upload marks')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->url(fn (): string => BulkActivityMarksImportPage::urlForTest(
                    (string) ($this->markSheet['test_label'] ?? ''),
                    isset($this->markSheet['activity_type_id']) ? (int) $this->markSheet['activity_type_id'] : null,
                    isset($this->markSheet['batch_id']) ? (int) $this->markSheet['batch_id'] : null,
                    $this->markSheet['date']?->format('Y-m-d') ?? null,
                )),
            Action::make('back')
                ->label('Back to tests')
                ->url(\App\Filament\Resources\ActivitySessions\ActivitySessionResource::getUrl('index')),
        ];
    }

    public function publishResults(ResultDeclarationService $declarations): void
    {
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
                    'canSendWhatsApp' => CrmAccess::can(Auth::user(), CrmPermission::WhatsappCampaigns),
                    'resultStatus' => $this->resultStatus(),
                    'canPublish' => CrmAccess::can(Auth::user(), CrmPermission::MarksImport),
                    'canIssueMarksheet' => Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false,
                    'studentMarksheets' => $this->studentMarksheetsByStudentId(),
                ]),
        ]);
    }
}
