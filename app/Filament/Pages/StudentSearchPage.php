<?php

namespace App\Filament\Pages;

use App\Enums\LeadSource;
use App\Enums\CrmPermission;
use App\Support\CrmAccess;
use App\Filament\Forms\EnquiryFormSchema;
use App\Services\EnquiryService;
use App\Services\StudentSearchService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\MeetingForOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class StudentSearchPage extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'Search Student';

    protected static ?string $title = 'Search Student';

    protected static ?int $navigationSort = 10;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_LEADS;

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::StudentsView);
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('students.search');
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    /**
     * @var array<string, mixed>
     */
    public array $search = [
        'mobile' => null,
        'name' => null,
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public bool $showEnquiryForm = false;

    public bool $isSearching = false;

    public bool $showNameNotFound = false;

    public bool $showNameSearch = false;

    public ?string $lookedUpMobile = null;

    public ?string $searchedName = null;

    /**
     * @var Collection<int, \App\Models\Student>|null
     */
    public ?Collection $searchResults = null;

    public function mount(): void
    {
        if (filled(request()->query('mobile'))) {
            $this->search['mobile'] = request()->query('mobile');
            $this->performSearch(app(StudentSearchService::class));
        }
    }

    public function searchForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('search')
            ->components([
                View::make('filament.pages.partials.student-search-hero'),
                TextInput::make('mobile')
                    ->label('Mobile number')
                    ->tel()
                    ->placeholder('9876543210')
                    ->maxLength(10)
                    ->inputMode('numeric')
                    ->autocomplete('tel')
                    ->autofocus()
                    ->prefixIcon(Heroicon::OutlinedDevicePhoneMobile)
                    ->disabled(fn (): bool => $this->isSearching)
                    ->live(debounce: 400)
                    ->afterStateUpdated(function (): void {
                        if ($this->isSearching) {
                            return;
                        }

                        $this->resetLookupState();

                        $mobile = $this->normalizedMobile();

                        if (strlen($mobile) === 10 && preg_match('/^[6-9]\d{9}$/', $mobile)) {
                            $this->performSearch();
                        }
                    })
                    ->rules([
                        'nullable',
                        'regex:/^[6-9]\d{9}$/',
                    ])
                    ->validationAttribute('mobile number')
                    ->extraAttributes(['class' => 'fi-student-search-mobile-field col-span-full'])
                    ->extraInputAttributes([
                        'class' => 'fi-student-search-mobile-input text-center font-mono text-xl tracking-[0.2em] sm:text-2xl',
                        'inputmode' => 'numeric',
                        'pattern' => '[0-9]*',
                    ]),
                View::make('filament.pages.partials.student-search-name-toggle'),
                View::make('filament.pages.partials.student-search-name-section'),
                TextInput::make('name')
                    ->label('Student name')
                    ->placeholder('e.g. Rahul, Priya Sharma')
                    ->maxLength(255)
                    ->prefixIcon(Heroicon::OutlinedUser)
                    ->disabled(fn (): bool => $this->isSearching)
                    ->visible(fn (): bool => $this->showNameSearch
                        || filled($this->search['name'])
                        || filled($this->searchedName))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (): void {
                        if ($this->isSearching || filled($this->normalizedMobile())) {
                            return;
                        }

                        $name = $this->normalizedName();

                        if (strlen($name) >= 2) {
                            $this->performSearch();
                        }
                    })
                    ->extraAttributes(['class' => 'fi-student-search-name-field col-span-full']),
            ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(
            EnquiryFormSchema::forSearchPageQuickEntry($this->lookedUpMobile),
        );
    }

    public function lookupMobile(?StudentSearchService $searchService = null): void
    {
        $this->performSearch($searchService);
    }

    public function performSearch(?StudentSearchService $searchService = null): void
    {
        if ($this->isSearching) {
            return;
        }

        $this->syncSearchFormState();

        $mobile = $this->normalizedMobile();
        $name = $this->normalizedName();
        $service = $searchService ?? app(StudentSearchService::class);

        if (filled($mobile)) {
            if (! preg_match('/^[6-9]\d{9}$/', $mobile)) {
                Notification::make()
                    ->title('Invalid mobile')
                    ->body('Mobile must be 10 digits starting with 6–9.')
                    ->warning()
                    ->send();

                return;
            }

            $this->resetLookupState();
            $this->isSearching = true;

            $result = $service->search($mobile, null, null, null);

            if ($result['outcome'] === StudentSearchService::OUTCOME_FOUND && $result['student']) {
                $this->redirect(
                    StudentProfilePage::getUrl(['record' => $result['student']->id]),
                    navigate: true,
                );

                return;
            }

            $this->isSearching = false;
            $this->lookedUpMobile = $mobile;
            $this->showEnquiryForm = true;
            $this->form->fill($this->quickEnquiryDefaults($mobile));

            return;
        }

        if (filled($name)) {
            if (strlen($name) < 2) {
                Notification::make()
                    ->title('Name too short')
                    ->body('Type at least 2 letters to search by name.')
                    ->warning()
                    ->send();

                return;
            }

            $this->resetLookupState();
            $this->isSearching = true;
            $this->searchedName = $name;

            $result = $service->search(null, $name, null, null);
            $this->isSearching = false;

            if ($result['outcome'] === StudentSearchService::OUTCOME_FOUND && $result['student']) {
                $this->searchResults = new Collection([$result['student']]);

                return;
            }

            if ($result['outcome'] === StudentSearchService::OUTCOME_MULTIPLE) {
                $this->searchResults = $result['students'];

                return;
            }

            $this->showNameNotFound = true;

            return;
        }

        Notification::make()
            ->title('Enter mobile or name')
            ->body('Type a 10-digit mobile number or at least 2 letters of the student name.')
            ->warning()
            ->send();
    }

    protected function syncSearchFormState(): void
    {
        try {
            $state = $this->getSchema('searchForm')->getState();
            $this->search = array_merge($this->search, $state);
        } catch (\Throwable) {
            // Fall back to bound Livewire state when schema is unavailable.
        }
    }

    public function clearSearch(): void
    {
        $this->search = [
            'mobile' => null,
            'name' => null,
        ];
        $this->showNameSearch = false;
        $this->resetLookupState();
    }

    protected function resetLookupState(): void
    {
        $this->showEnquiryForm = false;
        $this->lookedUpMobile = null;
        $this->searchedName = null;
        $this->searchResults = null;
        $this->showNameNotFound = false;
        $this->isSearching = false;
    }

    protected function normalizedMobile(): string
    {
        return preg_replace('/\D/', '', (string) ($this->search['mobile'] ?? ''));
    }

    protected function normalizedName(): string
    {
        return trim((string) ($this->search['name'] ?? ''));
    }

    protected function searchLoadingLabel(): string
    {
        $mobile = $this->normalizedMobile();

        if (filled($mobile)) {
            return $mobile;
        }

        return $this->normalizedName();
    }

    protected function searchLoadingMode(): string
    {
        return filled($this->normalizedMobile()) ? 'mobile' : 'name';
    }

    /**
     * @return array<string, mixed>
     */
    protected function quickEnquiryDefaults(string $mobile): array
    {
        return [
            'mobile' => $mobile,
            'meeting_for' => MeetingForOptions::defaultValue(),
        ];
    }

    public function saveEnquiry(EnquiryService $enquiryService): void
    {
        $data = $this->form->getState();
        $data['mobile'] = $data['mobile'] ?? $this->lookedUpMobile;

        $enquiry = $enquiryService->create($data, Auth::user(), LeadSource::WalkIn);

        Notification::make()
            ->title('Enquiry created')
            ->body("Enquiry {$enquiry->enquiry_number} saved successfully.")
            ->success()
            ->send();

        $this->redirect(
            StudentProfilePage::getUrl(['record' => $enquiry->student_id]),
            navigate: true,
        );
    }

    public function content(Schema $schema): Schema
    {
        $pageClass = 'fi-student-search-page';

        if ($this->isSearching || $this->hasActiveSearch()) {
            $pageClass .= ' fi-student-search-page--active';
        }

        if ($this->showEnquiryForm) {
            $pageClass .= ' fi-student-search-page--enquiry';
        }

        if ($this->showEnquiryForm) {
            $components = [
                View::make('filament.pages.partials.student-search-enquiry-context')
                    ->viewData(fn (): array => [
                        'lookedUpMobile' => $this->lookedUpMobile,
                    ]),
                $this->getEnquiryFormComponent()
                    ->extraAttributes(['class' => 'fi-student-search-form fi-student-search-enquiry-form']),
            ];
        } else {
            $components = [
                Form::make([EmbeddedSchema::make('searchForm')])
                    ->id('searchForm')
                    ->livewireSubmitHandler('performSearch')
                    ->footer([
                        Actions::make([
                            Action::make('continue')
                                ->label(fn (): string => filled($this->normalizedName()) && ! filled($this->normalizedMobile())
                                    ? 'Search by name'
                                    : 'Look up mobile')
                                ->icon(Heroicon::OutlinedMagnifyingGlass)
                                ->submit('performSearch')
                                ->extraAttributes([
                                    'wire:loading.attr' => 'disabled',
                                    'wire:loading.class' => 'opacity-70 cursor-wait',
                                    'wire:target' => 'performSearch',
                                    'class' => 'fi-student-search-submit',
                                ]),
                            Action::make('clearSearch')
                                ->label('Clear')
                                ->color('gray')
                                ->icon(Heroicon::OutlinedXMark)
                                ->visible(fn (): bool => $this->hasActiveSearch() || filled($this->search['mobile']) || filled($this->search['name']))
                                ->action('clearSearch'),
                        ])
                            ->alignment(Alignment::Start),
                    ])
                    ->extraAttributes(['class' => 'fi-student-search-form fi-student-search-form--primary']),
            ];

            if ($this->isSearching) {
                $components[] = View::make('filament.pages.partials.student-search-loading')
                    ->viewData(fn (): array => [
                        'mobile' => $this->searchLoadingLabel(),
                        'mode' => $this->searchLoadingMode(),
                    ]);
            } elseif ($this->searchResults !== null && $this->searchResults->isNotEmpty()) {
                $components[] = View::make('filament.pages.partials.student-search-results')
                    ->viewData(fn (): array => [
                        'searchResults' => $this->searchResults,
                        'searchedName' => $this->searchedName,
                    ]);
            } elseif ($this->showNameNotFound) {
                $components[] = View::make('filament.pages.partials.student-search-empty')
                    ->viewData(fn (): array => [
                        'searchedName' => $this->searchedName,
                    ]);
            }
        }

        return $schema
            ->components($components)
            ->extraAttributes(['class' => $pageClass]);
    }

    protected function hasActiveSearch(): bool
    {
        return filled($this->lookedUpMobile)
            || filled($this->searchedName)
            || ($this->searchResults !== null && $this->searchResults->isNotEmpty())
            || $this->showNameNotFound
            || $this->showEnquiryForm;
    }

    public function getEnquiryFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('enquiryForm')
            ->livewireSubmitHandler('saveEnquiry')
            ->footer([
                Actions::make([
                    Action::make('saveEnquiry')
                        ->label('Save enquiry & open profile')
                        ->submit('saveEnquiry')
                        ->icon(Heroicon::OutlinedArrowRight)
                        ->extraAttributes(['class' => 'fi-student-search-enquiry-save']),
                ])
                    ->fullWidth(),
            ]);
    }
}
