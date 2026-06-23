<?php

namespace App\Filament\Pages;

use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Enums\CrmPermission;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityMarksWhatsAppService;
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

    public function mount(): void
    {
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
                ]),
        ]);
    }
}
