<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\AddsHomeworkModal;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\Batch;
use App\Models\HomeworkAssignment;
use App\Services\HomeworkSubmissionService;
use App\Services\HomeworkWhatsAppService;
use App\Support\CrmAccess;
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
    use AddsHomeworkModal;
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

    public ?int $sendConfirmBatchId = null;

    public ?int $duplicateSendBatchId = null;

    /** @var array<int|string, string> */
    public array $missingSubjectReasons = [];

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::homeworkReview();
    }

    public function getSubheading(): ?string
    {
        return 'Tap a class. If a subject is still missing, say what happened. Then send one WhatsApp to parents.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'batch_id' => null,
            'homework_date' => now()->toDateString(),
        ]);

        $this->openHomeworkFromRequest();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Date')
                ->description(fn (): string => $this->dateString() === now()->toDateString()
                    ? 'Today is selected. New homework can be typed until 9:00 PM. Sending stays open for today only.'
                    : 'This date has passed. You can read what was saved. Adding and sending stay closed.')
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
                            $this->sendConfirmBatchId = null;
                            $this->duplicateSendBatchId = null;
                            $this->missingSubjectReasons = [];
                        }),
                    Hidden::make('batch_id'),
                ])
                ->columns(2),
            Section::make('Classes')
                ->description('A red line means a teacher has not submitted. Open the class, then send. You will be asked about each missing subject before parents get the message.')
                ->schema([
                    View::make('filament.pages.partials.homework-review-pending')
                        ->viewData(function (): array {
                            $date = $this->dateString();

                            $service = app(HomeworkSubmissionService::class);

                            return [
                                'desk' => $service->deskForDate($date),
                                'openBatchId' => (int) ($this->openBatchId ?? 0),
                                'dateLabel' => Carbon::parse($date)->format('d M Y'),
                                'isToday' => $date === now()->toDateString(),
                                'canEnter' => $service->canEnterHomework($date),
                                'canSend' => $service->canSendHomework($date),
                                'sendConfirmBatchId' => (int) ($this->sendConfirmBatchId ?? 0),
                                'duplicateSendBatchId' => (int) ($this->duplicateSendBatchId ?? 0),
                                'duplicateSendLabel' => $this->duplicateSendLabel(),
                                'sendReportUrl' => ParentMessageSendsPage::canAccess()
                                    ? ParentMessageSendsPage::getUrl()
                                    : null,
                                'missingSubjects' => (int) ($this->sendConfirmBatchId ?? 0) > 0
                                    ? $service->missingSubjectsForBatch((int) $this->sendConfirmBatchId, $date)
                                    : [],
                                'missingSubjectReasons' => $this->missingSubjectReasons,
                                'windowNote' => $service->homeworkWindowNote($date),
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
                ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)
                    && app(HomeworkSubmissionService::class)->canSendHomework($this->dateString())),
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
                        'batchId' => $batchId,
                        'board' => $board,
                        'dateLabel' => Carbon::parse($date)->format('d M Y'),
                        'canEnter' => $service->canEnterHomework($date),
                        'canSend' => $service->canSendHomework($date),
                        'lastCombinedSendResult' => $this->lastCombinedSendResult,
                    ];
                }),
        ]);
    }

    protected function duplicateSendLabel(): string
    {
        $batchId = (int) ($this->duplicateSendBatchId ?? 0);

        if ($batchId < 1) {
            return 'This class';
        }

        return Batch::query()->find($batchId)?->displayLabel() ?? 'This class';
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

    public function askDuplicateSend(int $batchId): void
    {
        if ($batchId < 1) {
            return;
        }

        $this->form->fill([
            ...($this->data ?? []),
            'batch_id' => $batchId,
        ]);

        if ($this->pauseSendForMissingSubjects($batchId)) {
            return;
        }

        $alreadySent = HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->whereDate('homework_date', $this->dateString())
            ->where('status', HomeworkAssignmentStatus::Sent->value)
            ->exists();

        if (! $alreadySent) {
            $this->openClass($batchId);
            $this->sendCombined();

            return;
        }

        $this->duplicateSendBatchId = $batchId;
    }

    public function cancelDuplicateSend(): void
    {
        $this->duplicateSendBatchId = null;
    }

    public function confirmDuplicateSend(): void
    {
        $batchId = (int) ($this->duplicateSendBatchId ?? 0);
        $this->duplicateSendBatchId = null;

        if ($batchId < 1) {
            return;
        }

        $this->form->fill([
            ...($this->data ?? []),
            'batch_id' => $batchId,
        ]);
        $this->openClass($batchId);
        $this->sendCombined();
    }

    public function sendCombinedForBatch(int $batchId): void
    {
        if ($batchId < 1) {
            return;
        }

        $this->form->fill([
            ...($this->data ?? []),
            'batch_id' => $batchId,
        ]);

        if ($this->pauseSendForMissingSubjects($batchId)) {
            return;
        }

        $this->openClass($batchId);
        $this->sendCombined();
    }

    public function setMissingReason(int $subjectId, string $reason): void
    {
        if (! in_array($reason, ['teacher_absent', 'no_homework'], true)) {
            return;
        }

        $this->missingSubjectReasons[$subjectId] = $reason;
    }

    public function cancelClosedSend(): void
    {
        $this->sendConfirmBatchId = null;
        $this->missingSubjectReasons = [];
    }

    public function confirmClosedSend(): void
    {
        $user = Auth::user();
        $batchId = (int) ($this->sendConfirmBatchId ?? 0);

        if (! $user || $batchId < 1) {
            return;
        }

        try {
            app(HomeworkSubmissionService::class)->closeMissingSubjects(
                $user,
                $batchId,
                $this->dateString(),
                $this->missingSubjectReasons,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Choose what happened for each subject.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        $this->sendConfirmBatchId = null;
        $this->missingSubjectReasons = [];
        $this->openClass($batchId);
        $this->sendCombined();
    }

    protected function pauseSendForMissingSubjects(int $batchId): bool
    {
        if ($batchId < 1) {
            return false;
        }

        $service = app(HomeworkSubmissionService::class);

        if (! $service->canSendHomework($this->dateString())) {
            return false;
        }

        $missing = $service->missingSubjectsForBatch($batchId, $this->dateString());

        if ($missing === []) {
            $this->sendConfirmBatchId = null;

            return false;
        }

        $this->sendConfirmBatchId = $batchId;
        $this->missingSubjectReasons = [];

        return true;
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

        $batchId = (int) ($this->data['batch_id'] ?? 0);

        if ($this->pauseSendForMissingSubjects($batchId)) {
            return;
        }

        $result = app(HomeworkSubmissionService::class)->combinedSend(
            $user,
            (int) ($this->data['batch_id'] ?? 0),
            $this->dateString(),
            app(HomeworkWhatsAppService::class)->defaultCombinedTemplateName(),
        );
        $this->lastCombinedSendResult = $result;
        $costNote = '';

        if (CrmAccess::canSeeMessageCost($user)) {
            $cost = number_format((float) ($result['estimated_total_cost'] ?? 0), 2);
            $currency = (string) ($result['currency'] ?? 'INR');
            $costNote = ' Estimated cost: '.$currency.' '.$cost.'.';
        }

        if ($result['sent'] > 0 && ($result['failed'] ?? 0) === 0) {
            Notification::make()
                ->title('Sent to parents')
                ->body($result['sent'].' message(s) sent covering '.$result['subjects'].' subject(s).'.$costNote)
                ->success()
                ->send();

            return;
        }

        if ($result['sent'] > 0) {
            Notification::make()
                ->title('Partially sent')
                ->body($result['sent'].' sent, '.$result['failed'].' failed.'.$costNote.' See recipient details below.')
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
