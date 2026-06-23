<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Dashboard;
use App\Enums\RoleName;
use App\Services\InstituteOnboardingService;
use App\Support\CrmHint;
use App\Support\InstituteOnboarding;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class FirstRunSetup extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $navigationLabel = 'Setup Wizard';

    protected static ?string $title = 'Welcome — set up your institute';

    protected static ?string $slug = 'setup';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function mount(InstituteOnboardingService $onboarding): void
    {
        if (InstituteOnboarding::isComplete()) {
            $this->redirect(Dashboard::getUrl());

            return;
        }

        $this->form->fill($onboarding->suggestedDefaults());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CrmHint::placeholder('setup.wizard'),
            Section::make('1. Branding & contact')
                ->schema([
                    TextInput::make('name')->label('Institute name')->required()->maxLength(255),
                    TextInput::make('tagline')->required()->maxLength(255),
                    TextInput::make('number_prefix')
                        ->label('Record ID prefix')
                        ->maxLength(12)
                        ->helperText(CrmHint::field('record_prefix')),
                    TextInput::make('phone')->tel()->required(),
                    TextInput::make('email')->email()->required(),
                    TextInput::make('whatsapp')->label('WhatsApp number (digits only)'),
                    Textarea::make('address')->rows(2)->columnSpanFull(),
                    TextInput::make('city'),
                    TextInput::make('hours')->label('Office hours'),
                    TextInput::make('established')->label('Established year')->maxLength(10),
                ])
                ->columns(2),
            Section::make('2. Website hero')
                ->schema([
                    TextInput::make('hero_title')->label('Hero title')->required()->columnSpanFull(),
                    Textarea::make('hero_subtitle')->label('Hero subtitle')->rows(3)->columnSpanFull(),
                    Textarea::make('about')->label('About text (homepage)')->rows(4)->columnSpanFull(),
                    Repeater::make('hero_stats')
                        ->label('Hero highlights')
                        ->schema([
                            TextInput::make('title')->required()->maxLength(40),
                            TextInput::make('subtitle')->required()->maxLength(80),
                        ])
                        ->columns(2)
                        ->defaultItems(3)
                        ->minItems(1)
                        ->maxItems(4)
                        ->columnSpanFull(),
                ]),
            Section::make('3. Labels & website sections')
                ->description('Use your own terms — e.g. Class instead of Programme, Section instead of Batch.')
                ->schema([
                    TextInput::make('label_course')->label('Course / programme label')->required(),
                    TextInput::make('label_batch')->label('Batch / section label')->required(),
                    TextInput::make('label_roll_number')->label('Student ID label')->required(),
                    TextInput::make('label_programmes_heading')->label('Programmes section title')->required(),
                    Toggle::make('home_show_courses_section')
                        ->label('Show programmes section on homepage')
                        ->helperText('You can still control which individual courses appear under Academics → Courses.')
                        ->default(true)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public function complete(InstituteOnboardingService $onboarding): void
    {
        $data = $this->form->getState();
        $onboarding->complete($data);

        Notification::make()
            ->title('Setup complete')
            ->body('Add your programmes under Academics → Courses, then customize the website from Website → Site Content.')
            ->success()
            ->send();

        $this->redirect(Dashboard::getUrl());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('firstRunSetupForm')
            ->livewireSubmitHandler('complete')
            ->footer([
                Actions::make([
                    Action::make('complete')
                        ->label('Finish setup')
                        ->submit('complete')
                        ->icon(Heroicon::OutlinedCheckCircle),
                ]),
            ]);
    }
}
