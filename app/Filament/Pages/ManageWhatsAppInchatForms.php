<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppInchatAudience;
use App\Enums\WhatsAppInchatFieldType;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\WhatsAppInchatForm;
use App\Services\WhatsAppFlowReplyList;
use App\Services\WhatsAppFlowService;
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

    protected static ?string $title = 'Flow builder';

    protected static string|UnitEnum|null $navigationGroup = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $editingId = null;

    public bool $showEditor = false;

    public string $replyFlow = 'all';

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    public function mount(): void
    {
        $this->showEditor = false;
        $this->editingId = null;
        $this->form->fill($this->blankState());
    }

    public function startNewFlow(): void
    {
        $this->editingId = null;
        $this->showEditor = true;
        $this->form->fill($this->blankState());
    }

    public function editFlow(int $formId, WhatsAppInchatFormService $forms): void
    {
        $form = WhatsAppInchatForm::query()->with('fields')->find($formId);

        if (! $form) {
            Notification::make()->title('That flow was not found')->warning()->send();

            return;
        }

        $this->editingId = $form->id;
        $this->showEditor = true;
        $this->form->fill($forms->formState($form));
    }

    public function closeFlowEditor(): void
    {
        $this->showEditor = false;
        $this->editingId = null;
    }

    public function publishVisit(WhatsAppFlowService $flows): void
    {
        $result = $flows->publishCampusVisit();

        if (($result['status'] ?? '') !== 'success') {
            Notification::make()
                ->title('Could not publish the visit form')
                ->body((string) ($result['error'] ?? 'WhatsApp did not publish the visit form.'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Visit form published')
            ->body('Staff can send it from the chat. A parent can also open it by typing visit.')
            ->success()
            ->send();
    }

    public function save(WhatsAppInchatFormService $forms): void
    {
        $saved = $this->store($forms);

        if (! $saved) {
            return;
        }

        Notification::make()
            ->title('Form saved')
            ->body('Saved here only. Press Save and publish when it should open on WhatsApp.')
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
            Section::make('Flow')
                ->description('Name it, pick one word, then the questions. Do not use the word visit.')
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(80)
                        ->placeholder('Admission enquiry'),
                    TextInput::make('trigger_word')
                        ->label('Word they type')
                        ->required()
                        ->maxLength(20)
                        ->placeholder('admission'),
                    TextInput::make('button_label')
                        ->label('Button text')
                        ->maxLength(20)
                        ->placeholder('Open')
                        ->default('Open'),
                    Toggle::make('enabled')
                        ->label('On')
                        ->default(true),
                    Repeater::make('fields')
                        ->label('Questions')
                        ->schema([
                            TextInput::make('id')->hidden()->dehydrated(),
                            TextInput::make('label')
                                ->label('Question')
                                ->required()
                                ->maxLength(80)
                                ->placeholder('Your name'),
                            Select::make('field_type')
                                ->label('Answer type')
                                ->options(WhatsAppInchatFieldType::options())
                                ->required()
                                ->native(false)
                                ->live()
                                ->default(WhatsAppInchatFieldType::ShortText->value),
                            Toggle::make('is_required')
                                ->label('Must answer')
                                ->default(false),
                            Textarea::make('choices')
                                ->label('Choices, one per line')
                                ->rows(3)
                                ->visible(fn (Get $get): bool => $get('field_type') === WhatsAppInchatFieldType::Choices->value)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->addActionLabel('Add a question')
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Question')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->maxItems(8)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        $components = [
            View::make('filament.pages.partials.whatsapp-inchat-forms-list')
                ->viewData(fn (): array => [
                    'flows' => app(WhatsAppInchatFormService::class)->listRows(),
                    'visitPublished' => filled(app(WhatsAppFlowService::class)->savedFlowId()),
                    'showEditor' => $this->showEditor,
                    'replies' => app(WhatsAppFlowReplyList::class)->rows($this->replyFlow),
                    'replyFlow' => $this->replyFlow,
                ]),
        ];

        if ($this->showEditor) {
            $components[] = $this->getFormContentComponent();
        }

        return $schema->components($components);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('whatsappInchatForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('publish')
                        ->label('Save and publish')
                        ->action(fn () => $this->publish(app(WhatsAppInchatFormService::class))),
                    Action::make('save')
                        ->label('Save for later')
                        ->color('gray')
                        ->submit('save'),
                    Action::make('close')
                        ->label('Close')
                        ->color('gray')
                        ->action(fn () => $this->closeFlowEditor()),
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
            'trigger_word' => '',
            'button_label' => 'Open',
            'enabled' => true,
            'fields' => [
                [
                    'id' => null,
                    'field_type' => WhatsAppInchatFieldType::ShortText->value,
                    'label' => 'Your name',
                    'is_required' => true,
                    'choices' => '',
                ],
            ],
        ];
    }

    protected function store(WhatsAppInchatFormService $forms): ?WhatsAppInchatForm
    {
        $state = $this->form->getState();
        $existing = $this->editingId
            ? WhatsAppInchatForm::query()->find($this->editingId)
            : null;
        $state['audience'] = $existing?->audience->value ?? WhatsAppInchatAudience::EducationLead->value;
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
