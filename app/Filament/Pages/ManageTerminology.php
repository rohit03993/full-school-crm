<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Support\CrmHint;
use App\Support\InstituteTerminology;
use App\Support\CrmNavigation;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ManageTerminology extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $navigationLabel = 'Terminology';

    protected static ?string $title = 'Terminology & labels';

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('setup.terminology');
    }

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
        $labels = InstituteTerminology::all();

        $this->form->fill([
            'course' => $labels['course'],
            'batch' => $labels['batch'],
            'roll_number' => $labels['roll_number'],
            'programmes_heading' => $labels['programmes_heading'],
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CrmHint::placeholder('setup.terminology'),
            Section::make('Admin & CRM labels')
                ->description('These labels appear in forms, menus, and reports. Leave blank to use defaults for your institute type.')
                ->schema(collect(InstituteTerminology::KEYS)->map(
                    fn (string $description, string $key): TextInput => TextInput::make($key)
                        ->label($description)
                        ->maxLength(80)
                        ->placeholder(InstituteTerminology::defaultLabel($key)),
                )->values()->all()),
        ]);
    }

    public function save(): void
    {
        InstituteTerminology::save($this->form->getState());

        Notification::make()
            ->title('Terminology saved')
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
            ->id('terminologyForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save labels')
                        ->submit('save'),
                ]),
            ]);
    }
}
