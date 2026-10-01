<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\AddsHomeworkModal;
use App\Services\HomeworkSubmissionService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
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

class SubmitHomeworkPage extends Page
{
    use AddsHomeworkModal;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $title = 'Homework';

    protected static ?int $navigationSort = 44;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::submitHomework();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return false;
        }

        $user = Auth::user();

        if (! $user || CrmAccess::can($user, CrmPermission::HomeworkManage)) {
            return false;
        }

        return app(HomeworkSubmissionService::class)->canSubmit($user);
    }

    public function getSubheading(): ?string
    {
        return 'Your classes for the selected date. Type homework for empty subjects until 9:00 PM. Done and Not done open after admin approves. Admin still sends one WhatsApp to parents.';
    }

    public function mount(): void
    {
        $this->form->fill([
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
        $service = app(HomeworkSubmissionService::class);
        $user = Auth::user();

        return $schema->components([
            Section::make('Date')
                ->description('Today is selected. New homework can be typed until 9:00 PM. A past date can be opened to read, but typing stays closed.')
                ->schema([
                    DatePicker::make('homework_date')
                        ->label('Homework date')
                        ->native(false)
                        ->required()
                        ->maxDate(now())
                        ->live(),
                ])
                ->columns(2),
            Section::make('My classes')
                ->description('Homework you have given shows its status here. After admin approves it, or after it is sent to parents, you cannot change it.')
                ->schema([
                    View::make('filament.pages.partials.submit-homework')
                        ->viewData(function () use ($service, $user): array {
                            $date = $this->dateString();

                            return [
                                'desk' => $user
                                    ? $service->teacherDeskForDate($user, $date)
                                    : ['counts' => ['missing' => 0, 'submitted' => 0, 'approved' => 0, 'sent' => 0], 'groups' => []],
                                'dateLabel' => Carbon::parse($date)->format('d M Y'),
                                'isToday' => $date === now()->toDateString(),
                                'canEnter' => $service->canEnterHomework($date),
                                'windowNote' => $service->homeworkWindowNote($date),
                                'checkBaseUrl' => HomeworkCheckPage::getUrl(),
                                'checkDate' => $date,
                            ];
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('submitHomeworkForm'),
        ]);
    }

    public function deleteSubmission(int $assignmentId): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        try {
            app(HomeworkSubmissionService::class)->deleteSubmission($user, $assignmentId);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not remove.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        Notification::make()->title('Homework removed')->success()->send();
    }

    protected function dateString(): string
    {
        $value = $this->data['homework_date'] ?? null;

        return filled($value) ? Carbon::parse((string) $value)->toDateString() : now()->toDateString();
    }
}
