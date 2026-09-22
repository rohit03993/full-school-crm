<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Services\HomeworkSubmissionService;
use App\Services\HomeworkWhatsAppService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    protected static ?string $title = 'Homework Review';

    protected static ?int $navigationSort = 47;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $lastCombinedSendResult = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::homeworkReview();
    }

    public function getSubheading(): ?string
    {
        return 'Pending homework for the selected date is listed first (teacher + class/section). Open a class to approve subjects and send ONE combined WhatsApp to parents — only subjects with homework are included.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'batch_id' => null,
            'homework_date' => now()->toDateString(),
            'course_subject_id' => null,
            'title' => '',
            'description' => '',
            'attachment' => null,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $service = app(HomeworkSubmissionService::class);

        return $schema->components([
            Section::make('Date')
                ->description('Today is selected. Pick a past date to see that day’s pending homework.')
                ->schema([
                    DatePicker::make('homework_date')
                        ->label('Homework date')
                        ->native(false)
                        ->required()
                        ->maxDate(now())
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->lastCombinedSendResult = null;
                        }),
                ])
                ->columns(2),
            Section::make('Pending homework')
                ->description('Teacher submissions waiting for your approval. Grouped by class and section.')
                ->schema([
                    View::make('filament.pages.partials.homework-review-pending')
                        ->viewData(function (): array {
                            $date = $this->dateString();

                            return [
                                'pending' => app(HomeworkSubmissionService::class)->pendingReviewForDate($date),
                                'selectedBatchId' => (int) ($this->data['batch_id'] ?? 0),
                                'dateLabel' => Carbon::parse($date)->format('d M Y'),
                                'isToday' => $date === now()->toDateString(),
                            ];
                        })
                        ->columnSpanFull(),
                ]),
            Section::make('Open a class')
                ->description('Use this if you want to add a subject yourself, or after you tap Open class on a pending row above.')
                ->schema([
                    Select::make('batch_id')
                        ->label('Class')
                        ->options(fn (): array => $service->allBatchOptions())
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->data['course_subject_id'] = null;
                            $this->lastCombinedSendResult = null;
                        }),
                ])
                ->columns(2),
            Section::make('Add / edit a subject')
                ->description('Use this if a teacher is absent — you can create or replace any subject\'s homework. Saved here it is approved immediately and ready to send.')
                ->collapsed()
                ->schema([
                    Select::make('course_subject_id')
                        ->label('Subject')
                        ->options(function () use ($service): array {
                            $batchId = (int) ($this->data['batch_id'] ?? 0);

                            return $batchId > 0 ? $service->allSubjectOptionsForBatch($batchId) : [];
                        })
                        ->searchable()
                        ->native(false)
                        ->live(),
                    TextInput::make('title')
                        ->label('Title (optional)')
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Homework details')
                        ->rows(3)
                        ->columnSpanFull(),
                    FileUpload::make('attachment')
                        ->label('PDF or image (optional)')
                        ->disk('public')
                        ->directory('homework')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ])
                        ->maxSize(10240)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
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
            'course_subject_id' => null,
        ]);
        $this->lastCombinedSendResult = null;
    }

    public function saveAdmin(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $state = $this->form->getState();

        if ((int) ($state['course_subject_id'] ?? 0) < 1) {
            Notification::make()->title('Pick a subject to add homework')->warning()->send();

            return;
        }

        try {
            $assignment = app(HomeworkSubmissionService::class)->submit($user, [
                'batch_id' => (int) ($state['batch_id'] ?? 0),
                'course_subject_id' => (int) ($state['course_subject_id'] ?? 0),
                'homework_date' => $this->dateString(),
                'title' => (string) ($state['title'] ?? ''),
                'description' => (string) ($state['description'] ?? ''),
                'file_path' => $this->attachmentPath($state['attachment'] ?? null),
            ], asAdmin: true);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not save.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        $this->data['course_subject_id'] = null;
        $this->data['title'] = '';
        $this->data['description'] = '';
        $this->data['attachment'] = null;

        Notification::make()
            ->title('Saved & approved')
            ->body($assignment->courseSubject?->displayLabel().' homework is approved and ready to send.')
            ->success()
            ->send();
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

        app(HomeworkSubmissionService::class)->deleteSubmission($user, $assignmentId, asAdmin: true);

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

    protected function attachmentPath(mixed $attachment): ?string
    {
        if (is_array($attachment)) {
            $first = $attachment[array_key_first($attachment)] ?? reset($attachment);

            return filled($first) ? (string) $first : null;
        }

        return filled($attachment) ? (string) $attachment : null;
    }

    protected function dateString(): string
    {
        $value = $this->data['homework_date'] ?? null;

        return filled($value) ? Carbon::parse((string) $value)->toDateString() : now()->toDateString();
    }
}
