<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\MeetingForOptions;
use Filament\Actions\Action;
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

class ManageMeetingForOptions extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Meeting for';

    protected static ?string $title = 'Meeting for options';

    protected static ?int $navigationSort = 35;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('setup.meeting_for');
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
        $this->form->fill([
            'options' => MeetingForOptions::all(),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CrmHint::placeholder('setup.meeting_for'),
            Section::make('Dropdown choices')
                ->description('These appear on Search Student, new enquiry forms, and lead filters. Rename, reorder, add, or hide options — no code changes needed.')
                ->schema([
                    Repeater::make('options')
                        ->label('Options')
                        ->schema([
                            TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(80)
                                ->placeholder('e.g. Enquiry, Admission, Marketing'),
                            TextInput::make('value')
                                ->label('Internal key')
                                ->maxLength(60)
                                ->helperText('Leave blank to auto-generate from the label. Avoid changing after data exists.'),
                            Toggle::make('is_active')
                                ->label('Show in forms')
                                ->default(true),
                            Toggle::make('is_default')
                                ->label('Default selection')
                                ->helperText('Pre-selected on new enquiry forms. Only one should be default — the first checked wins on save.'),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'New option')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        MeetingForOptions::save($state['options'] ?? []);

        $this->form->fill([
            'options' => MeetingForOptions::all(),
        ]);

        Notification::make()
            ->title('Meeting for options saved')
            ->body('Search Student and enquiry forms will use these choices immediately.')
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
            ->id('meetingForOptionsForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save options')
                        ->submit('save'),
                ]),
            ]);
    }
}
