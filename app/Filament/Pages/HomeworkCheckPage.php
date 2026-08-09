<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Services\HomeworkCheckService;
use App\Support\CrmNavigation;
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

class HomeworkCheckPage extends Page
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::HomeworkManage;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::Homework;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Homework check';

    protected static ?string $title = 'Homework Check';

    protected static ?int $navigationSort = 46;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'Mark Done or Not Done per student. WhatsApp to parent is sent only for Not Done (dedicated template).';
    }

    public function mount(): void
    {
        $this->form->fill([
            'batch_id' => null,
            'student_id' => null,
            'course_subject_id' => null,
            'topic' => '',
            'student_search' => '',
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $service = app(HomeworkCheckService::class);
        $user = Auth::user();

        return $schema->components([
            Section::make('Mark homework')
                ->schema([
                    Select::make('batch_id')
                        ->label('Class')
                        ->options(fn (): array => $user ? $service->batchOptionsFor($user) : [])
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->data['student_id'] = null;
                            $this->data['course_subject_id'] = null;
                        }),
                    TextInput::make('student_search')
                        ->label('Search student')
                        ->placeholder('Type a name…')
                        ->live(debounce: 300)
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                    Select::make('student_id')
                        ->label('Student')
                        ->options(function () use ($service): array {
                            $batchId = (int) ($this->data['batch_id'] ?? 0);
                            if ($batchId < 1) {
                                return [];
                            }

                            return $service->studentOptionsForBatch(
                                $batchId,
                                $this->data['student_search'] ?? null,
                            );
                        })
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                    Select::make('course_subject_id')
                        ->label('Subject')
                        ->options(function () use ($service, $user): array {
                            $batchId = (int) ($this->data['batch_id'] ?? 0);
                            if ($batchId < 1 || ! $user) {
                                return [];
                            }

                            return $service->subjectOptionsForBatch($user, $batchId);
                        })
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                    Textarea::make('topic')
                        ->label('Homework topic')
                        ->placeholder('e.g. Chapter 5 – Complete Questions 1 to 10')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('homeworkCheckForm'),
            View::make('filament.pages.partials.homework-check-actions')
                ->viewData(fn (): array => [
                    'recent' => filled($this->data['batch_id'] ?? null)
                        ? app(HomeworkCheckService::class)->recentForBatch((int) $this->data['batch_id'])
                        : collect(),
                ]),
        ]);
    }

    public function markDone(HomeworkCheckService $service): void
    {
        $this->mark($service, HomeworkCheckStatus::Done);
    }

    public function markNotDone(HomeworkCheckService $service): void
    {
        $this->mark($service, HomeworkCheckStatus::NotDone);
    }

    protected function mark(HomeworkCheckService $service, HomeworkCheckStatus $status): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        try {
            $result = $service->mark(
                $user,
                (int) ($this->data['batch_id'] ?? 0),
                (int) ($this->data['student_id'] ?? 0),
                (int) ($this->data['course_subject_id'] ?? 0),
                (string) ($this->data['topic'] ?? ''),
                $status,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not save.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        $title = $status === HomeworkCheckStatus::Done ? 'Marked Done' : 'Marked Not Done';
        $body = $result['whatsapp']['message'] ?? '';

        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();

        $this->data['student_id'] = null;
    }
}
