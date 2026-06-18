<?php

namespace App\Filament\Pages;

use App\Enums\InstituteType;
use App\Enums\RoleName;
use App\Filament\Resources\AcademicSessions\AcademicSessionResource;
use App\Filament\Resources\ActivityTypes\ActivityTypeResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\AcademicSession;
use App\Services\InstituteProfileService;
use App\Support\InstituteProfile;
use App\Support\InstituteSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
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
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

class InstituteSetup extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Institute Setup';

    protected static ?string $title = 'Institute Setup';

    protected static ?int $navigationSort = 0;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'institute_type' => InstituteProfile::type()->value,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Deployment profile')
                ->description('Each installation serves one kind of institute. Pick a type below — admin, enquiries, courses, and the public website update immediately.')
                ->schema([
                    Select::make('institute_type')
                        ->label('This CRM is for')
                        ->options(collect(InstituteType::cases())->mapWithKeys(
                            fn (InstituteType $type): array => [$type->value => $type->label()],
                        ))
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => $this->applyInstituteType($state))
                        ->helperText(fn (): string => InstituteType::tryFrom($this->data['institute_type'] ?? '')?->description()
                            ?? 'Select school, coaching, or college.'),
                    Placeholder::make('profile_note')
                        ->label('')
                        ->content(fn (): HtmlString => new HtmlString(
                            '<p class="text-sm text-gray-600 dark:text-gray-400">'
                            .'Changing the type updates programme filters, enquiry options, and the public courses page straight away. '
                            .'Default programmes for the new type are added automatically. Existing student records are kept.'
                            .'</p>'
                        ))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function applyInstituteType(?string $value): void
    {
        if (blank($value)) {
            return;
        }

        $type = InstituteType::from($value);
        $result = app(InstituteProfileService::class)->apply($type);

        if (! $result['changed']) {
            return;
        }

        Notification::make()
            ->title('Institute profile updated')
            ->body("Now configured as {$type->label()}. Courses, enquiries, and the website reflect this type immediately.")
            ->success()
            ->send();
    }

    public function save(): void
    {
        $this->applyInstituteType($this->form->getState()['institute_type'] ?? null);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.institute-setup-snapshot')
                ->viewData(fn (): array => [
                    'snapshot' => $this->instituteSnapshot(),
                ]),
            $this->getFormContentComponent(),
            View::make('filament.pages.partials.institute-setup-links')
                ->viewData(fn (): array => [
                    'links' => $this->setupLinks(),
                ]),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('instituteSetupForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Apply profile')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }

    /**
     * @return array<int, array{label: string, description: string, url: string, icon: string}>
     */
    public function setupLinks(): array
    {
        $type = InstituteProfile::type();

        return [
            [
                'label' => 'Academic Sessions',
                'description' => "Academic years for {$type->label()} batches (e.g. 2025–26).",
                'url' => AcademicSessionResource::getUrl(),
                'icon' => 'heroicon-o-calendar-days',
            ],
            [
                'label' => 'Activity Types',
                'description' => 'Exams, mock tests, workshops — configurable per institute.',
                'url' => ActivityTypeResource::getUrl(),
                'icon' => 'heroicon-o-adjustments-horizontal',
            ],
            [
                'label' => 'Courses / Programmes',
                'description' => $type->programmeLabel().' listings for this '.$type->label().'.',
                'url' => CourseResource::getUrl(),
                'icon' => 'heroicon-o-academic-cap',
            ],
            [
                'label' => 'Institute Settings',
                'description' => 'Receipt logo, PDF header/footer for receipts, ID cards, and reports.',
                'url' => ManageInstituteSettings::getUrl(),
                'icon' => 'heroicon-o-building-office-2',
            ],
            [
                'label' => 'Website Content',
                'description' => 'Public site branding, hero text, contact details, and gallery.',
                'url' => ManageSiteContent::getUrl(),
                'icon' => 'heroicon-o-globe-alt',
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function instituteSnapshot(): array
    {
        $session = AcademicSession::current();
        $type = InstituteProfile::type();

        return [
            'name' => InstituteSettings::brandName(),
            'type' => $type->label(),
            'prefix' => InstituteSettings::numberPrefix(),
            'session' => $session?->name,
        ];
    }
}
