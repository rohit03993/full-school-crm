<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Services\HomeworkSubmissionService;
use App\Services\HomeworkWhatsAppService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class HomeworkReviewPage extends Page
{
    use RequiresCrmPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::HomeworkManage;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::Homework;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $title = 'Homework';

    protected static ?int $navigationSort = 47;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $lastCombinedSendResult = null;

    public ?int $openBatchId = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::homeworkReview();
    }

    public function getSubheading(): ?string
    {
        return 'Today’s desk by class and section. Approve what teachers sent, then send ONE combined WhatsApp — only subjects with homework are included.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'batch_id' => null,
            'homework_date' => now()->toDateString(),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Date')
                ->description('Today is selected. Pick a past date to review that day.')
                ->schema([
                    DatePicker::make('homework_date')
                        ->label('Homework date')
                        ->native(false)
                        ->required()
                        ->maxDate(now())
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->lastCombinedSendResult = null;
                            $this->openBatchId = null;
                        }),
                    Hidden::make('batch_id'),
                ])
                ->columns(2),
            Section::make('Pending homework')
                ->description('Tap a class to see every subject. Waiting first, then ready to send, then already sent.')
                ->schema([
                    View::make('filament.pages.partials.homework-review-pending')
                        ->viewData(function (): array {
                            $date = $this->dateString();

                            return [
                                'desk' => app(HomeworkSubmissionService::class)->deskForDate($date),
                                'openBatchId' => (int) ($this->openBatchId ?? 0),
                                'dateLabel' => Carbon::parse($date)->format('d M Y'),
                                'isToday' => $date === now()->toDateString(),
                                'submitUrl' => SubmitHomeworkPage::getUrl(),
                                'checkUrl' => HomeworkCheckPage::getUrl(),
                                'historyUrl' => HomeworkAssignmentResource::getUrl('index'),
                            ];
                        })
                        ->columnSpanFull(),
                ]),
            Section::make('Send to parents')
                ->description(function (): string {
                    $template = app(HomeworkWhatsAppService::class)->defaultCombinedTemplateName();

                    if (filled($template)) {
                        return 'Uses template "'.$template.'" from WhatsApp → Automations → Homework. Change it there if needed.';
                    }

                    return 'Pick a combined template on WhatsApp → Automations → Homework before sending.';
                })
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('combined_template_notice')
                        ->hiddenLabel()
                        ->content(function (): \Illuminate\Support\HtmlString {
                            $template = app(HomeworkWhatsAppService::class)->defaultCombinedTemplateName();
                            $automationsUrl = e(ManageWhatsAppSettings::getUrl(['automation' => 'homework']));
                            $label = filled($template)
                                ? '<strong>'.e($template).'</strong>'
                                : '<span class="text-danger-600">No template selected</span>';

                            return new \Illuminate\Support\HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-300">Combined WhatsApp template: '.$label
                                .' — <a href="'.$automationsUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Open Automations → Homework</a></p>'
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('homeworkReviewForm'),
            View::make('filament.pages.partials.homework-review-board')
                ->viewData(function (): array {
                    $user = Auth::user();
                    $batchId = (int) ($this->data['batch_id'] ?? 0);
                    $date = $this->dateString();
                    $ready = $user && $batchId > 0 && filled($date);
                    $service = app(HomeworkSubmissionService::class);

                    $board = $ready
                        ? $service->boardForClassDate($user, $batchId, $date)
                        : ['subjects' => [], 'summary' => ['total' => 0, 'submitted' => 0, 'approved' => 0, 'sent' => 0, 'missing' => 0]];

                    return [
                        'ready' => (bool) $ready,
                        'board' => $board,
                        'dateLabel' => Carbon::parse($date)->format('d M Y'),
                        'lastCombinedSendResult' => $this->lastCombinedSendResult,
                    ];
                }),
        ]);
    }

    public function openClass(int $batchId): void
    {
        if ($batchId < 1) {
            return;
        }

        $this->form->fill([
            ...($this->data ?? []),
            'batch_id' => $batchId,
        ]);
        $this->openBatchId = $batchId;
        $this->lastCombinedSendResult = null;
    }

    public function toggleDeskSection(int $batchId): void
    {
        if ($batchId < 1) {
            return;
        }

        $this->openBatchId = (int) $this->openBatchId === $batchId ? null : $batchId;
    }

    public function approvePending(int $batchId): void
    {
        $user = Auth::user();

        if (! $user || $batchId < 1) {
            return;
        }

        try {
            $count = app(HomeworkSubmissionService::class)->approvePendingForClassDate($user, $batchId, $this->dateString());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not approve.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        $this->openClass($batchId);

        if ($count < 1) {
            Notification::make()->title('Nothing waiting to approve')->warning()->send();

            return;
        }

        Notification::make()
            ->title('Approved')
            ->body($count.' subject(s) approved and ready to send.')
            ->success()
            ->send();
    }

    public function sendCombinedForBatch(int $batchId): void
    {
        if ($batchId < 1) {
            return;
        }

        $this->openClass($batchId);
        $this->sendCombined();
    }

    public function approve(int $assignmentId): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        try {
            app(HomeworkSubmissionService::class)->approve($user, $assignmentId);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not approve.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        Notification::make()->title('Approved')->success()->send();
    }

    public function remove(int $assignmentId): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        try {
            app(HomeworkSubmissionService::class)->deleteSubmission($user, $assignmentId, asAdmin: true);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not remove.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        Notification::make()->title('Removed')->success()->send();
    }

    public function sendCombined(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $result = app(HomeworkSubmissionService::class)->combinedSend(
            $user,
            (int) ($this->data['batch_id'] ?? 0),
            $this->dateString(),
            app(HomeworkWhatsAppService::class)->defaultCombinedTemplateName(),
        );
        $this->lastCombinedSendResult = $result;
        $cost = number_format((float) ($result['estimated_total_cost'] ?? 0), 2);
        $currency = (string) ($result['currency'] ?? 'INR');

        if ($result['sent'] > 0 && ($result['failed'] ?? 0) === 0) {
            Notification::make()
                ->title('Sent to parents')
                ->body($result['sent'].' message(s) sent covering '.$result['subjects'].' subject(s). Estimated cost: '.$currency.' '.$cost.'.')
                ->success()
                ->send();

            return;
        }

        if ($result['sent'] > 0) {
            Notification::make()
                ->title('Partially sent')
                ->body($result['sent'].' sent, '.$result['failed'].' failed. Estimated cost: '.$currency.' '.$cost.'. See recipient details below.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Nothing sent')
            ->body((string) ($result['error'] ?? 'No messages went out.'))
            ->danger()
            ->persistent()
            ->send();
    }

    protected function dateString(): string
    {
        $value = $this->data['homework_date'] ?? null;

        return filled($value) ? Carbon::parse((string) $value)->toDateString() : now()->toDateString();
    }
}
