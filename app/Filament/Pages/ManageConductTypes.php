<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Support\ConductTypeOptions;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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
use UnitEnum;

class ManageConductTypes extends Page
{
    use CanUseDatabaseTransactions;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 36;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::conductTypes();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::conductTypes();
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('setup.conduct_types');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'options' => ConductTypeOptions::all(),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CrmHint::placeholder('setup.conduct_types'),
            Section::make('Indiscipline types')
                ->description('These appear as tick boxes on the student Conduct tab. Add a name, rename one, or hide one the school does not use.')
                ->schema([
                    Repeater::make('options')
                        ->label('Types')
                        ->schema([
                            TextInput::make('label')
                                ->label('Name')
                                ->required()
                                ->maxLength(80)
                                ->placeholder('e.g. Late, Uniform, Abusive language'),
                            Hidden::make('value'),
                            Toggle::make('is_active')
                                ->label('Show on the student form')
                                ->default(true),
                        ])
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'New type')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        ConductTypeOptions::save($state['options'] ?? []);

        $this->form->fill([
            'options' => ConductTypeOptions::all(),
        ]);

        Notification::make()
            ->title('Conduct types saved')
            ->body('The student Conduct tab will use this list.')
            ->success()
            ->send();
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
            ->id('conductTypesForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save types')
                        ->submit('save'),
                ]),
            ]);
    }
}
