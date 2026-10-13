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

    protected static ?string $title = 'In-chat forms';

    protected static string|UnitEnum|null $navigationGroup = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $editingId = null;

    /**
     * @var list<array{id: int, name: string, audience: string, trigger_word: string, state: string}>
     */
    public array $savedForms = [];

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
        $this->refreshList($forms);
        $this->startNewForm();
    }

    public function startNewForm(): void
    {
        $this->editingId = null;
        $this->form->fill($this->blankState());
    }

    public function editForm(int $formId): void
    {
        $form = WhatsAppInchatForm::query()->find($formId);

        if (! $form) {
            return;
        }

        $forms = app(WhatsAppInchatFormService::class);
        $this->editingId = $form->id;
        $this->form->fill($forms->formState($form));
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
        $this->refreshList($forms);

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
            Section::make('1. Who is this for?')
                ->schema([
                    Select::make('audience')
                        ->label('Who')
                        ->options(WhatsAppInchatAudience::options())
                        ->required()
                        ->native(false),
                ]),
            Section::make('2. What word opens it?')
                ->schema([
                    TextInput::make('trigger_word')
                        ->label('Word')
                        ->required()
                        ->maxLength(20)
                        ->helperText('One word, such as fees. The word visit is already used.'),
                    TextInput::make('name')
                        ->label('Form name')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Parents see this name on the button message.'),
                ])
                ->columns(2),
            Section::make('3. Questions')
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
                                ->helperText('Only for Choices. One choice per line. Each line can be 30 characters.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Question')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->maxItems(8)
                        ->columnSpanFull(),
                ]),
            Section::make('4. Publish')
                ->schema([
                    TextInput::make('button_label')
                        ->label('Button text')
                        ->maxLength(20)
                        ->default('Open form')
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
            View::make('filament.pages.partials.whatsapp-inchat-forms-list')
                ->viewData([
                    'savedForms' => $this->savedForms,
                ]),
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
            'name' => '',
            'audience' => WhatsAppInchatAudience::Anyone->value,
            'trigger_word' => '',
            'button_label' => 'Open form',
            'enabled' => true,
            'fields' => [
                [
                    'id' => null,
                    'field_type' => WhatsAppInchatFieldType::ShortText->value,
                    'label' => '',
                    'is_required' => true,
                    'choices' => '',
                ],
            ],
        ];
    }

    protected function store(WhatsAppInchatFormService $forms): ?WhatsAppInchatForm
    {
        $state = $this->form->getState();
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
        $this->refreshList($forms);

        return $result['form'];
    }

    protected function refreshList(WhatsAppInchatFormService $forms): void
    {
        $this->savedForms = $forms->listRows();
    }
}
