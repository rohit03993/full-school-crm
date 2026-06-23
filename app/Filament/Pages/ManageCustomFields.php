<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Models\CustomFieldDefinition;
use App\Services\CustomFieldService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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

class ManageCustomFields extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Extra Student Fields';

    protected static ?string $title = 'Extra Student Fields';

    protected static ?int $navigationSort = 40;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('setup.custom_fields');
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
            'student_fields' => $this->mapDefinitions(CustomFieldService::ENTITY_STUDENT),
            'enquiry_fields' => $this->mapDefinitions(CustomFieldService::ENTITY_ENQUIRY),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapDefinitions(string $entity): array
    {
        return CustomFieldDefinition::query()
            ->where('entity', $entity)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CustomFieldDefinition $field): array => [
                'field_key' => $field->field_key,
                'label' => $field->label,
                'field_type' => $field->field_type,
                'options' => collect($field->options ?? [])->map(fn (string $option): array => ['value' => $option])->values()->all(),
                'is_required' => $field->is_required,
                'is_active' => $field->is_active,
            ])
            ->all();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CrmHint::placeholder('setup.custom_fields'),
            Section::make('Student profile fields')
                ->description('Extra fields shown when staff click Edit Details on a student profile.')
                ->schema([
                    Repeater::make('student_fields')
                        ->label('Fields')
                        ->schema(self::fieldRepeaterSchema())
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'New field')
                        ->columnSpanFull(),
                ]),
            Section::make('Enquiry / lead fields')
                ->description('Extra fields on Search Student new enquiry and add-enquiry forms.')
                ->schema([
                    Repeater::make('enquiry_fields')
                        ->label('Fields')
                        ->schema(self::fieldRepeaterSchema())
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'New field')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    protected static function fieldRepeaterSchema(): array
    {
        return [
            TextInput::make('label')
                ->required()
                ->maxLength(120),
            TextInput::make('field_key')
                ->label('Key (optional)')
                ->maxLength(60)
                ->helperText(CrmHint::field('custom_field_key')),
            Select::make('field_type')
                ->label('Type')
                ->options([
                    'text' => 'Text',
                    'number' => 'Number',
                    'date' => 'Date',
                    'textarea' => 'Long text',
                    'select' => 'Dropdown',
                ])
                ->default('text')
                ->required()
                ->native(false)
                ->live(),
            Repeater::make('options')
                ->label('Dropdown options')
                ->schema([
                    TextInput::make('value')->required()->maxLength(80),
                ])
                ->visible(fn (callable $get): bool => $get('field_type') === 'select')
                ->defaultItems(0)
                ->columnSpanFull(),
            Toggle::make('is_required')->label('Required'),
            Toggle::make('is_active')->label('Active')->default(true),
        ];
    }

    public function save(CustomFieldService $customFields): void
    {
        $state = $this->form->getState();

        $customFields->syncDefinitions(
            CustomFieldService::ENTITY_STUDENT,
            $state['student_fields'] ?? [],
        );

        $customFields->syncDefinitions(
            CustomFieldService::ENTITY_ENQUIRY,
            $state['enquiry_fields'] ?? [],
        );

        Notification::make()
            ->title('Custom fields saved')
            ->body('Student and enquiry fields are live immediately.')
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
            ->id('customFieldsForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save custom fields')
                        ->submit('save'),
                ]),
            ]);
    }
}
