<?php

namespace App\Filament\Pages;

use App\Enums\LeadSource;
use App\Enums\MeetingFor;
use App\Filament\Forms\EnquiryFormSchema;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use App\Services\EnquiryService;
use App\Services\StudentSearchService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class StudentSearchPage extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'Search Student';

    protected static ?string $title = 'Search Student';

    protected static ?int $navigationSort = -100;

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    /**
     * @var array<string, mixed>
     */
    public array $search = [
        'mobile' => null,
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public bool $showEnquiryForm = false;

    public ?string $lookedUpMobile = null;

    public function mount(): void
    {
        if (filled(request()->query('mobile'))) {
            $this->search['mobile'] = request()->query('mobile');
            $this->lookupMobile(app(StudentSearchService::class));
        }
    }

    public function searchForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('search')
            ->components([
                Section::make('Enter Mobile Number')
                    ->description('Type the student mobile — we will open their profile or the quick enquiry form automatically.')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->schema([
                        TextInput::make('mobile')
                            ->label('Mobile Number')
                            ->tel()
                            ->placeholder('10-digit mobile number')
                            ->maxLength(10)
                            ->inputMode('numeric')
                            ->autocomplete('tel')
                            ->autofocus()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (): void {
                                $this->resetLookupState();

                                $mobile = $this->normalizedMobile();

                                if (strlen($mobile) === 10 && preg_match('/^[6-9]\d{9}$/', $mobile)) {
                                    $this->lookupMobile();
                                }
                            })
                            ->rule('nullable|regex:/^[6-9]\d{9}$/')
                            ->validationAttribute('mobile number'),
                    ]),
            ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(
            EnquiryFormSchema::forQuickStaffEntry($this->lookedUpMobile),
        );
    }

    public function lookupMobile(?StudentSearchService $searchService = null): void
    {
        $mobile = $this->normalizedMobile();

        if (blank($mobile)) {
            Notification::make()
                ->title('Enter mobile number')
                ->body('Please type a 10-digit Indian mobile number.')
                ->warning()
                ->send();

            return;
        }

        if (! preg_match('/^[6-9]\d{9}$/', $mobile)) {
            Notification::make()
                ->title('Invalid mobile')
                ->body('Mobile must be 10 digits starting with 6–9.')
                ->warning()
                ->send();

            return;
        }

        $this->resetLookupState();
        $this->lookedUpMobile = $mobile;

        $result = ($searchService ?? app(StudentSearchService::class))->search(
            $mobile,
            null,
            null,
            null,
        );

        if ($result['outcome'] === StudentSearchService::OUTCOME_FOUND && $result['student']) {
            $this->redirect(StudentProfilePage::getUrl(['record' => $result['student']->id]));

            return;
        }

        $this->showEnquiryForm = true;
        $this->form->fill($this->quickEnquiryDefaults($mobile));
    }

    protected function resetLookupState(): void
    {
        $this->showEnquiryForm = false;
        $this->lookedUpMobile = null;
    }

    protected function normalizedMobile(): string
    {
        return preg_replace('/\D/', '', (string) ($this->search['mobile'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    protected function quickEnquiryDefaults(string $mobile): array
    {
        return [
            'mobile' => $mobile,
            'meeting_for' => MeetingFor::FolksIndia->value,
        ];
    }

    public function openEnquiry(int $enquiryId): void
    {
        $enquiry = Enquiry::query()->findOrFail($enquiryId);
        $this->redirect(StudentProfilePage::getUrl(['record' => $enquiry->student_id]));
    }

    public function saveEnquiry(EnquiryService $enquiryService): void
    {
        $data = $this->form->getState();
        $data['meeting_with_user_id'] = Auth::id();
        $data['mobile'] = $data['mobile'] ?? $this->lookedUpMobile;

        $enquiry = $enquiryService->create($data, Auth::user(), LeadSource::WalkIn);

        Notification::make()
            ->title('Enquiry created')
            ->body("Enquiry {$enquiry->enquiry_number} saved successfully.")
            ->success()
            ->send();

        $this->redirect(StudentProfilePage::getUrl(['record' => $enquiry->student_id]));
    }

    public function content(Schema $schema): Schema
    {
        $searchService = app(StudentSearchService::class);

        $recentEnquiries = $searchService->recentEnquiries();

        $components = [
            $this->getSearchFormComponent(),
            Section::make('Recent Leads')
                ->description($recentEnquiries->isEmpty()
                    ? 'Latest 5 enquiries — see CRM → All Leads for full list'
                    : 'Latest '.$recentEnquiries->count().' — tap to open profile')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->collapsed($recentEnquiries->isEmpty())
                ->compact()
                ->schema([
                    View::make('filament.pages.partials.student-search-recent-enquiries')
                        ->viewData(fn (): array => [
                            'recentEnquiries' => $recentEnquiries,
                            'allLeadsUrl' => EnquiryResource::getUrl('index'),
                        ]),
                ]),
        ];

        if ($this->showEnquiryForm) {
            $components[] = Section::make('New Enquiry')
                ->description("No student found for {$this->lookedUpMobile} — fill quick details below.")
                ->schema([
                    $this->getEnquiryFormComponent(),
                ])
                ->compact();
        }

        return $schema->components($components);
    }

    public function getSearchFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('searchForm')])
            ->id('searchForm')
            ->livewireSubmitHandler('lookupMobile')
            ->footer([
                Actions::make([
                    Action::make('continue')
                        ->label('Continue')
                        ->icon(Heroicon::OutlinedArrowRight)
                        ->submit('lookupMobile'),
                ])
                    ->alignment(Alignment::Start)
                    ->fullWidth(),
            ]);
    }

    public function getEnquiryFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('enquiryForm')
            ->livewireSubmitHandler('saveEnquiry')
            ->footer([
                Actions::make([
                    Action::make('saveEnquiry')
                        ->label('Save & Open Profile')
                        ->submit('saveEnquiry')
                        ->icon(Heroicon::OutlinedArrowRight),
                ])
                    ->fullWidth(),
            ]);
    }
}
