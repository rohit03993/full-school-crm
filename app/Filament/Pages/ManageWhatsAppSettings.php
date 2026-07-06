<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Services\PalDigitalTemplateSyncService;
use App\Services\PalDigitalWhatsAppService;
use App\Services\WhatsAppProviderResolver;
use App\Services\WhatsAppSettingsService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\CrmNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\HtmlString;
use UnitEnum;

class ManageWhatsAppSettings extends Page
{
    use CanUseDatabaseTransactions;
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog8Tooth;

    protected static ?string $navigationLabel = 'Automations';

    protected static ?string $title = 'WhatsApp Automations';

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return CrmHint::text('setup.whatsapp');
    }

    public function mount(WhatsAppSettingsService $settings): void
    {
        $this->form->fill($settings->getFormData());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CrmHint::placeholder('setup.whatsapp'),
            Placeholder::make('active_provider_notice')
                ->label('')
                ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderActiveProviderNotice())
                ->columnSpanFull()
                ->visible(fn (): bool => app(WhatsAppProviderResolver::class)->metaOverridesPalDigital()),
            Section::make('Legacy — Pal Digital / waservice')
                ->description('Only needed when Meta WhatsApp is turned off. Not used for sends while Meta routing is active.')
                ->collapsed()
                ->visible(fn (): bool => ! app(WhatsAppProviderResolver::class)->metaOverridesPalDigital())
                ->schema([
                    Placeholder::make('api_key_status')
                        ->label('')
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderApiKeyStatus())
                        ->columnSpanFull(),
                    TextInput::make('pal_digital_api_key')
                        ->label('Replace integration key')
                        ->password()
                        ->revealable()
                        ->placeholder('Leave blank to keep the saved key')
                        ->helperText('Only paste a new wsk. key here when replacing. If a key is already saved, leave this empty when saving other settings.')
                        ->extraInputAttributes([
                            'autocomplete' => 'new-password',
                            'autocorrect' => 'off',
                            'autocapitalize' => 'off',
                            'spellcheck' => 'false',
                            'data-1p-ignore' => 'true',
                            'data-lpignore' => 'true',
                            'data-form-type' => 'other',
                        ]),
                    TextInput::make('pal_digital_api_url')
                        ->label('Send API URL')
                        ->placeholder('https://wa.paldigital.in/api/v1')
                        ->required(fn (WhatsAppSettingsService $settings): bool => ! $settings->hasStoredApiKey())
                        ->helperText('Base /api/v1 is auto-completed to the send endpoint if needed.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Templates for automations')
                ->description(fn (): string => app(WhatsAppProviderResolver::class)->metaOverridesPalDigital()
                    ? 'Templates synced from Meta (Connection & Setup → Sync templates). Pick one for each automation below.'
                    : 'Step 1 — Save Pal Digital key and click Sync templates. Step 2 — Pick templates for each action below.')
                ->schema([
                    Placeholder::make('synced_templates_table')
                        ->label('')
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderSyncedTemplatesTable())
                        ->columnSpanFull(),
                ]),
            Section::make('Attendance & punch — parent WhatsApp')
                ->description('Match each real-world action to one approved Meta template.')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->schema([
                    Placeholder::make('attendance_automation_guide')
                        ->label('')
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderAttendanceAutomationGuide())
                        ->columnSpanFull(),
                    Toggle::make('punch_autosend_enabled')
                        ->label('Send parent WhatsApp on IN and OUT')
                        ->helperText('Master switch for all four templates below (machine + manual).')
                        ->columnSpanFull(),
                    Placeholder::make('machine_templates_heading')
                        ->label('')
                        ->content(new HtmlString('<p class="text-sm font-bold text-gray-950 dark:text-white">From biometric device (punch_logs)</p><p class="mt-0.5 text-xs text-gray-500">EasyTimePro writes to MySQL — CRM reads automatically.</p>'))
                        ->columnSpanFull(),
                    Select::make('punch_in_autosend_template_id')
                        ->label('Biometric check-in (IN)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('When student punches IN at the gate/device.'),
                    Select::make('punch_out_autosend_template_id')
                        ->label('Biometric check-out (OUT)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('When student punches OUT at the device.'),
                    Placeholder::make('manual_templates_heading')
                        ->label('')
                        ->content(new HtmlString('<p class="mt-2 text-sm font-bold text-gray-950 dark:text-white">From staff on Attendance screen</p><p class="mt-0.5 text-xs text-gray-500">Manual IN, Manual OUT, or batch IN/OUT buttons.</p>'))
                        ->columnSpanFull(),
                    Select::make('punch_manual_in_autosend_template_id')
                        ->label('Manual check-in (IN)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Staff marks IN. Leave blank to reuse Biometric IN template.'),
                    Select::make('punch_manual_out_autosend_template_id')
                        ->label('Manual check-out (OUT)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Staff marks OUT. Leave blank to reuse Biometric OUT template.'),
                    Toggle::make('attendance_autosend_enabled')
                        ->label('Legacy: batch-save template (optional)')
                        ->helperText('Old roll-call Present flow only. Manual IN/OUT on Attendance uses the four punch templates above.')
                        ->columnSpanFull(),
                    Select::make('attendance_autosend_template_id')
                        ->label('Fallback attendance template')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Used when a specific IN/OUT template (machine or manual) is left blank.'),
                ])
                ->columns(2),
            Section::make('Post-call auto message')
                ->description('After a connected outgoing call is logged, queue a WhatsApp using the selected template.')
                ->collapsed()
                ->schema([
                    Toggle::make('postcall_autosend_enabled')
                        ->label('Enable post-call WhatsApp'),
                    Select::make('postcall_autosend_template_id')
                        ->label('Template')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false),
                ])
                ->columns(2),
            Section::make('Campaign processing')
                ->description('For large campaigns (50+ students): batch size 10–20 and 2–5 second delay between batches is recommended.')
                ->schema([
                    TextInput::make('campaign_batch_size')
                        ->label('Batch size')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(10)
                        ->helperText('Messages sent per queue job run (max 50).'),
                    TextInput::make('campaign_batch_delay_seconds')
                        ->label('Delay between batches (seconds)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(60)
                        ->default(2)
                        ->helperText('Pause before the next batch — reduces rate-limit risk on large sends.'),
                ])
                ->columns(2),
        ]);
    }

    public function save(WhatsAppSettingsService $settings): void
    {
        $result = $settings->save($this->form->getState());

        if (! $result['ok']) {
            Notification::make()
                ->title('Could not save settings')
                ->body($result['message'] ?? 'Check the API key and URL.')
                ->danger()
                ->send();

            return;
        }

        $this->refillWhatsAppForm($settings);

        $metaActive = app(WhatsAppProviderResolver::class)->metaOverridesPalDigital();

        $body = $metaActive
            ? 'Automation and campaign settings are saved. Sends route through Meta.'
            : ($settings->hasValidStoredApiKey()
                ? 'Campaign and automation settings are saved.'
                : 'Settings saved. Paste a valid wsk. integration key to connect Pal Digital.');

        $body .= $settings->ignoredReplaceKeyNotice((bool) ($result['ignored_invalid_key_field'] ?? false));

        Notification::make()
            ->title($metaActive ? 'Automations saved' : 'WhatsApp settings saved')
            ->body(trim($body))
            ->success()
            ->send();
    }

    public function syncTemplates(
        WhatsAppSettingsService $settings,
        PalDigitalTemplateSyncService $sync,
    ): void {
        $saved = $settings->saveCredentials($this->form->getState(), strictKey: false);

        if (! $saved['ok']) {
            Notification::make()
                ->title('Could not sync templates')
                ->body($saved['message'] ?? 'Fix the API key first.')
                ->danger()
                ->send();

            return;
        }

        if (! $settings->hasValidStoredApiKey()) {
            Notification::make()
                ->title('Could not sync templates')
                ->body('Save a valid wsk. integration key first.')
                ->danger()
                ->send();

            return;
        }

        $result = $sync->sync();

        $this->refillWhatsAppForm($settings);

        $body = $result['message'].$settings->ignoredReplaceKeyNotice((bool) ($saved['ignored_invalid_key_field'] ?? false));

        CrmNotification::sendOutcome(
            $result['synced'].' template(s) synced',
            trim($body),
            $result['status'] === 'success',
            warningOnFailure: true,
        );
    }

    public function testConnection(
        WhatsAppSettingsService $settings,
        PalDigitalWhatsAppService $whatsapp,
    ): void {
        $saved = $settings->saveCredentials($this->form->getState(), strictKey: false);

        if (! $saved['ok']) {
            Notification::make()
                ->title('Connection check failed')
                ->body($saved['message'] ?? 'Fix the API key first.')
                ->danger()
                ->send();

            return;
        }

        if (! $settings->hasValidStoredApiKey()) {
            Notification::make()
                ->title('Connection check failed')
                ->body('Save a valid wsk. integration key first.')
                ->danger()
                ->send();

            return;
        }

        $result = $whatsapp->validateConnection();

        $this->refillWhatsAppForm($settings);

        $body = $result['message'].$settings->ignoredReplaceKeyNotice((bool) ($saved['ignored_invalid_key_field'] ?? false));

        CrmNotification::sendOutcome(
            $result['status'] === 'success' ? 'Connection OK' : 'Connection check failed',
            trim($body),
            $result['status'] === 'success',
        );
    }

    protected function refillWhatsAppForm(WhatsAppSettingsService $settings): void
    {
        $this->form->fill($settings->getFormData());
        $this->data['pal_digital_api_key'] = '';
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
            ->id('whatsappSettingsForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('syncTemplates')
                        ->label('Sync templates')
                        ->icon('heroicon-o-arrow-path')
                        ->action('syncTemplates')
                        ->visible(fn (): bool => ! app(WhatsAppProviderResolver::class)->metaOverridesPalDigital()),
                    Action::make('testConnection')
                        ->label('Test connection')
                        ->icon('heroicon-o-signal')
                        ->color('gray')
                        ->action('testConnection')
                        ->visible(fn (): bool => ! app(WhatsAppProviderResolver::class)->metaOverridesPalDigital()),
                    Action::make('save')
                        ->label('Save settings')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }
}
