<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppInchatAudience;
use App\Enums\WhatsAppInchatFieldType;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Enums\WhatsAppMessageSource;
use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppInchatForm;
use App\Support\WhatsAppCampusVisitFlowJson;
use App\Services\MetaWhatsAppService;
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

    public string $editingName = '';

    public bool $showEditor = false;

    public bool $editingVisit = false;

    /** Empty means the flow list. campus or form_12 means that flow is open. */
    public string $openFlowKey = '';

    /** all, on, or off. Filters the flow list only. */
    public string $listFilter = 'all';

    public string $replySearch = '';

    /** @var array<string, string> */
    public array $sendTemplateIds = [];

    public string $visitCourseLabel = 'Course';

    public string $visitDateLabel = 'Visit date';

    public string $visitNoteLabel = 'Note';

    public string $visitButton = 'Finish';

    public bool $visitNoteRequired = false;

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
        $this->openFlowKey = '';
        $this->editingVisit = false;
        $this->editingId = null;
        $this->editingName = '';
        $this->showEditor = true;
        $this->form->fill($this->blankState());
        $this->focusEditor();
    }

    public function openFlow(string $key): void
    {
        $this->openFlowKey = $key;
        $this->replySearch = '';
        $this->closeFlowEditor();
    }

    public function backToFlows(): void
    {
        $this->openFlowKey = '';
        $this->replySearch = '';
        $this->closeFlowEditor();
    }

    public function editFlow(int $formId, WhatsAppInchatFormService $forms): void
    {
        $form = WhatsAppInchatForm::query()->with('fields')->find($formId);

        if (! $form) {
            Notification::make()->title('That flow was not found')->warning()->send();

            return;
        }

        $this->openFlowKey = 'form_'.$form->id;
        $this->editingVisit = false;
        $this->editingId = $form->id;
        $this->editingName = $form->name;
        $this->showEditor = true;
        $this->form->fill($forms->formState($form));
        $this->focusEditor();
    }

    public function editVisit(): void
    {
        $copy = WhatsAppCampusVisitFlowJson::copy();
        $this->openFlowKey = 'campus';
        $this->showEditor = false;
        $this->editingId = null;
        $this->editingVisit = true;
        $this->visitCourseLabel = $copy['course_label'];
        $this->visitDateLabel = $copy['date_label'];
        $this->visitNoteLabel = $copy['note_label'];
        $this->visitButton = $copy['button_label'];
        $this->visitNoteRequired = $copy['note_required'];
        $this->focusEditor();
    }

    public function saveVisit(WhatsAppFlowService $flows): void
    {
        WhatsAppCampusVisitFlowJson::saveCopy([
            'course_label' => $this->visitCourseLabel,
            'date_label' => $this->visitDateLabel,
            'note_label' => $this->visitNoteLabel,
            'button_label' => $this->visitButton,
            'note_required' => $this->visitNoteRequired,
        ]);

        $result = $flows->publishCampusVisit();

        if (($result['status'] ?? '') !== 'success') {
            Notification::make()
                ->title('Words saved here')
                ->body((string) ($result['error'] ?? 'WhatsApp did not update the visit form yet.'))
                ->danger()
                ->send();

            return;
        }

        $this->editingVisit = false;

        Notification::make()
            ->title('Campus visit updated')
            ->body('The new words are on WhatsApp. Old visit replies stay in the list.')
            ->success()
            ->send();
    }

    public function setVisitEnabled(bool $on): void
    {
        WhatsAppCampusVisitFlowJson::saveCopy(['enabled' => $on]);

        Notification::make()
            ->title($on ? 'Campus visit is on' : 'Campus visit is off')
            ->body($on
                ? 'The word visit opens the form again.'
                : 'The word visit does nothing now. Old replies stay.')
            ->success()
            ->send();
    }

    public function setFlowEnabled(int $formId, bool $on): void
    {
        $form = WhatsAppInchatForm::query()->find($formId);

        if (! $form) {
            Notification::make()->title('That flow was not found')->warning()->send();

            return;
        }

        $form->update(['enabled' => $on]);

        Notification::make()
            ->title($on ? $form->name.' is on' : $form->name.' is off')
            ->body($on
                ? 'The word opens this form again.'
                : 'The word does nothing now. Replies and leads stay.')
            ->success()
            ->send();
    }

    public function deleteFlow(int $formId, WhatsAppInchatFormService $forms): void
    {
        $form = WhatsAppInchatForm::query()->find($formId);

        if (! $form) {
            Notification::make()->title('That flow was not found')->warning()->send();

            return;
        }

        $result = $forms->deleteIfUnused($form);

        if (($result['status'] ?? '') !== 'success') {
            Notification::make()
                ->title('This flow was not deleted')
                ->body((string) ($result['error'] ?? 'Turn it off instead.'))
                ->warning()
                ->send();

            return;
        }

        if ($this->editingId === $formId) {
            $this->closeFlowEditor();
        }

        $this->openFlowKey = '';
        $this->replySearch = '';

        Notification::make()
            ->title('Flow deleted')
            ->body('The word '.$result['word'].' can be used on a new flow.')
            ->success()
            ->send();
    }

    public function closeFlowEditor(): void
    {
        $this->showEditor = false;
        $this->editingVisit = false;
        $this->editingId = null;
        $this->editingName = '';
    }

    protected function focusEditor(): void
    {
        $this->js('document.getElementById("flow-editor")?.scrollIntoView({block:"start"})');
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

        $this->showEditor = false;

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
            Section::make(fn (): string => $this->editingId
                ? 'Editing '.$this->editingName
                : 'New flow')
                ->description('You are changing this flow only. The word visit belongs to Campus visit, even when that flow is off.')
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

    /**
     * Campus visit and every saved flow, in one list.
     *
     * @return list<array{key: string, id: int, name: string, word: string, state: string, on: bool, count: int, kind: string}>
     */
    public function flowCards(): array
    {
        $list = app(WhatsAppFlowReplyList::class);
        $visitOn = WhatsAppCampusVisitFlowJson::enabled();
        $published = filled(app(WhatsAppFlowService::class)->savedFlowId());

        $cards = [[
            'key' => 'campus',
            'id' => 0,
            'name' => 'Campus visit',
            'word' => 'visit',
            'state' => ! $visitOn ? 'Off' : ($published ? 'Live' : 'Not on WhatsApp'),
            'on' => $visitOn,
            'count' => $list->count('campus'),
            'kind' => 'visit',
        ]];

        foreach (app(WhatsAppInchatFormService::class)->listRows() as $flow) {
            $cards[] = [
                'key' => 'form_'.$flow['id'],
                'id' => (int) $flow['id'],
                'name' => $flow['name'],
                'word' => $flow['trigger_word'],
                'state' => $flow['state'],
                'on' => (bool) $flow['enabled'],
                'count' => $list->count($flow['name']),
                'kind' => 'form',
            ];
        }

        return $cards;
    }

    /**
     * @return array{flowKey: string, rows: list<array<string, mixed>>, count: int, templates: list<array{id: int, label: string}>}
     */
    public function openReplyView(): array
    {
        $card = collect($this->flowCards())->firstWhere('key', $this->openFlowKey);
        $flow = $card === null ? '' : ($card['kind'] === 'visit' ? 'campus' : $card['name']);

        return [
            'flowKey' => $this->openFlowKey,
            'rows' => $flow === '' ? [] : app(WhatsAppFlowReplyList::class)->rows($flow, 100, $this->replySearch),
            'count' => $flow === '' ? 0 : app(WhatsAppFlowReplyList::class)->count($flow, $this->replySearch),
            'templates' => $this->plainTemplates(),
        ];
    }

    /**
     * Approved templates with no fill-in words and no photo header.
     *
     * @return list<array{id: int, label: string}>
     */
    public function plainTemplates(): array
    {
        return MetaWhatsAppTemplate::query()
            ->where('status', 'APPROVED')
            ->where('is_active', true)
            ->where('param_count', 0)
            ->orderBy('name')
            ->get()
            ->reject(fn (MetaWhatsAppTemplate $template): bool => $this->templateNeedsFile($template))
            ->map(fn (MetaWhatsAppTemplate $template): array => [
                'id' => $template->id,
                'label' => $template->name,
            ])
            ->values()
            ->all();
    }

    public function sendReplyTemplate(string $flowKey): void
    {
        $templateId = (int) ($this->sendTemplateIds[$flowKey] ?? 0);
        $template = $templateId > 0 ? MetaWhatsAppTemplate::query()->find($templateId) : null;

        if (! $template || (int) $template->param_count !== 0 || $this->templateNeedsFile($template)) {
            Notification::make()->title('Choose a template with no blanks')->warning()->send();

            return;
        }

        $pack = $this->openReplyView();
        $people = [];

        if (($pack['flowKey'] ?? '') !== $flowKey) {
            Notification::make()->title('Open that flow first')->warning()->send();

            return;
        }

        foreach ($pack['rows'] ?? [] as $row) {
            $mobile = trim((string) ($row['mobile'] ?? ''));

            if ($mobile === '' || isset($people[$mobile])) {
                continue;
            }

            $people[$mobile] = $row['student_id'] ?? null;
        }

        if ($people === []) {
            Notification::make()->title('No numbers in this list')->warning()->send();

            return;
        }

        $meta = app(MetaWhatsAppService::class);
        $sent = 0;
        $failed = 0;

        foreach (array_slice($people, 0, 40, true) as $mobile => $studentId) {
            $result = $meta->sendTemplate(
                (string) $mobile,
                $template->name,
                [],
                filled($template->language) ? (string) $template->language : 'en',
                0,
                [
                    'student_id' => $studentId,
                    'message_source' => WhatsAppMessageSource::Campaign->value,
                ],
            );

            if (($result['status'] ?? '') === 'success') {
                $sent++;
            } else {
                $failed++;
            }
        }

        $notice = Notification::make()
            ->title($sent.' sent'.($failed > 0 ? ', '.$failed.' failed' : ''))
            ->body('Each number is used once. A template with blanks is not in this list.');

        if ($sent === 0) {
            $notice->danger();
        } else {
            $notice->success();
        }

        $notice->send();
    }

    protected function templateNeedsFile(MetaWhatsAppTemplate $template): bool
    {
        foreach ($template->components ?? [] as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));
            $format = strtoupper((string) ($component['format'] ?? ''));

            if ($type === 'HEADER' && in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                return true;
            }
        }

        return false;
    }

    public function content(Schema $schema): Schema
    {
        $components = [
            View::make('filament.pages.partials.whatsapp-inchat-forms-list')
                ->viewData(fn (): array => [
                    'part' => 'flows',
                    'cards' => $this->flowCards(),
                    'openKey' => $this->openFlowKey,
                    'listFilter' => $this->listFilter,
                    'editingVisit' => $this->editingVisit,
                ]),
        ];

        if ($this->showEditor) {
            $components[] = $this->getFormContentComponent();
        }

        if ($this->openFlowKey !== '') {
            $components[] = View::make('filament.pages.partials.whatsapp-flow-replies')
                ->viewData(fn (): array => $this->openReplyView());
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
                        ->label('Save draft')
                        ->color('gray')
                        ->link()
                        ->submit('save'),
                    Action::make('close')
                        ->label('Close')
                        ->color('gray')
                        ->link()
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
        $state['enabled'] = $existing?->enabled ?? true;
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
        $this->editingName = $result['form']->name;
        $this->openFlowKey = 'form_'.$result['form']->id;
        $this->form->fill($forms->formState($result['form']));

        return $result['form'];
    }
}
