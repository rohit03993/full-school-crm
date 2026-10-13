<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppInchatAudience;
use App\Enums\WhatsAppInchatFieldType;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\WhatsAppInchatForm;
use App\Services\WhatsAppInchatFormService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageWhatsAppInchatForms extends Page
{
    use RequiresCrmPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $slug = 'whatsapp-inchat-forms';

    protected static ?string $title = 'Education lead form';

    protected static string|UnitEnum|null $navigationGroup = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $editingId = null;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    public function mount(WhatsAppInchatFormService $forms): void
    {
        $form = WhatsAppInchatForm::query()
            ->where('audience', WhatsAppInchatAudience::EducationLead)
            ->orderBy('id')
            ->first();

        if ($form) {
            $this->editingId = $form->id;
            $this->form->fill($forms->formState($form));

            return;
        }

        $this->editingId = null;
        $this->form->fill($this->blankState());
    }

    public function save(WhatsAppInchatFormService $forms): void
    {
        $saved = $this->store($forms);

        if (! $saved) {
            return;
        }

        Notification::make()
            ->title('Form saved')
            ->body('Click Publish when the questions are ready. Parents do not see a draft.')
            ->success()
            ->send();
    }

    public function publish(WhatsAppInchatFormService $forms): void
    {
        $saved = $this->store($forms);

        if (! $saved) {
            return;
        }

        $result = $forms->publish($saved);

        $this->editingId = $saved->id;
        $this->form->fill($forms->formState($saved->fresh(['fields'])));

        if (($result['status'] ?? '') !== 'success') {
            Notification::make()
                ->title('Could not publish')
                ->body((string) ($result['error'] ?? 'WhatsApp did not publish this form.'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Form published')
            ->body('Parents can open it by typing the word.')
            ->success()
            ->send();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('1. Word')
                ->description('A parent types this one word. A child who is already in a class does not get the form.')
                ->schema([
                    TextInput::make('trigger_word')
                        ->label('Word')
                        ->required()
                        ->maxLength(20)
                        ->default('admission')
                        ->helperText('Suggested word: admission. The word visit is already used.'),
                    TextInput::make('name')
                        ->label('Form name')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Parents see this name above the button.'),
                ])
                ->columns(2),
            Section::make('2. Questions')
                ->description('These three questions are ready. You can edit them.')
                ->schema([
                    Repeater::make('fields')
                        ->label('Questions')
                        ->schema([
                            TextInput::make('id')->hidden()->dehydrated(),
                            Select::make('field_type')
                                ->label('Type')
                                ->options(WhatsAppInchatFieldType::options())
                                ->required()
                                ->native(false)
                                ->live()
                                ->default(WhatsAppInchatFieldType::ShortText->value),
                            TextInput::make('label')
                                ->label('Question')
                                ->required()
                                ->maxLength(80),
                            Toggle::make('is_required')
                                ->label('Required')
                                ->default(false),
                            Textarea::make('choices')
                                ->label('Choices')
                                ->rows(3)
                                ->helperText('One choice per line. Each line can be 30 characters.')
                                ->visible(fn (Get $get): bool => $get('field_type') === WhatsAppInchatFieldType::Choices->value)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Question')
                        ->defaultItems(3)
                        ->minItems(1)
                        ->maxItems(8)
                        ->columnSpanFull(),
                ]),
            Section::make('3. Publish')
                ->schema([
                    TextInput::make('button_label')
                        ->label('Button text')
                        ->maxLength(20)
                        ->default('Apply')
                        ->helperText('The words on the button in WhatsApp. Up to 20 characters.'),
                    Toggle::make('enabled')
                        ->label('On')
                        ->default(true)
                        ->helperText('Off means the word does nothing. The form stays saved.'),
                ])
                ->columns(2),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.whatsapp-inchat-forms-list'),
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('whatsappInchatForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save draft')
                        ->submit('save'),
                    Action::make('publish')
                        ->label('Publish')
                        ->action(fn () => $this->publish(app(WhatsAppInchatFormService::class))),
                ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankState(): array
    {
        return [
            'name' => 'Admission enquiry',
            'trigger_word' => 'admission',
            'button_label' => 'Apply',
            'enabled' => true,
            'fields' => [
                [
                    'id' => null,
                    'field_type' => WhatsAppInchatFieldType::ShortText->value,
                    'label' => 'Child name',
                    'is_required' => true,
                    'choices' => '',
                ],
                [
                    'id' => null,
                    'field_type' => WhatsAppInchatFieldType::CourseList->value,
                    'label' => 'Course',
                    'is_required' => true,
                    'choices' => '',
                ],
                [
                    'id' => null,
                    'field_type' => WhatsAppInchatFieldType::Note->value,
                    'label' => 'Note',
                    'is_required' => false,
                    'choices' => '',
                ],
            ],
        ];
    }

    protected function store(WhatsAppInchatFormService $forms): ?WhatsAppInchatForm
    {
        $state = $this->form->getState();
        $state['audience'] = WhatsAppInchatAudience::EducationLead->value;
        $result = $forms->save($state, $this->editingId);

        if (($result['status'] ?? '') !== 'success' || ! isset($result['form'])) {
            Notification::make()
                ->title('Could not save')
                ->body((string) ($result['error'] ?? 'Check the form and try again.'))
                ->danger()
                ->send();

            return null;
        }

        $this->editingId = $result['form']->id;
        $this->form->fill($forms->formState($result['form']));

        return $result['form'];
    }
}
