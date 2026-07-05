<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\MetaWhatsAppTemplate;
use App\Services\MetaWhatsAppService;
use App\Services\MetaWhatsAppSettingsService;
use App\Services\MetaWhatsAppTemplateSyncService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
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
use UnitEnum;

class ManageMetaWhatsAppSettings extends Page
{
    use CanUseDatabaseTransactions;
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::MetaWhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $navigationLabel = 'Connection & Setup';

    protected static ?string $title = 'Meta WhatsApp';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return CrmHint::text('setup.meta_whatsapp');
    }

    public function mount(MetaWhatsAppSettingsService $settings): void
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
            CrmHint::placeholder('setup.meta_whatsapp'),
            Section::make('Overview')
                ->schema([
                    Placeholder::make('dashboard_stats')
                        ->label('')
                        ->content(fn (MetaWhatsAppSettingsService $settings): \Illuminate\Support\HtmlString => $settings->renderDashboardStats())
                        ->columnSpanFull(),
                ]),
            Section::make('Status')
                ->description('Enable when credentials are saved. Pal Digital WhatsApp settings and automations are not changed by this toggle.')
                ->schema([
                    Placeholder::make('routing_banner')
                        ->label('')
                        ->content(fn (MetaWhatsAppSettingsService $settings): \Illuminate\Support\HtmlString => $settings->renderRoutingBanner())
                        ->columnSpanFull(),
                    Toggle::make('enabled')
                        ->label('Meta WhatsApp enabled')
                        ->helperText('When on, all campaigns and automations (punch, post-call, homework, marks) send via Meta instead of Pal Digital. Template names must exist in Synced Meta templates.')
                        ->columnSpanFull(),
                ]),
            Section::make('Meta Cloud API credentials')
                ->description('From Meta Business Manager → WhatsApp → API Setup.')
                ->schema([
                    Placeholder::make('access_token_status')
                        ->label('')
                        ->content(fn (MetaWhatsAppSettingsService $settings): \Illuminate\Support\HtmlString => $settings->renderAccessTokenStatus())
                        ->columnSpanFull(),
                    TextInput::make('phone_number_id')
                        ->label('Phone number ID')
                        ->required()
                        ->helperText('Meta phone_number_id for the WhatsApp sender.'),
                    TextInput::make('waba_id')
                        ->label('WhatsApp Business Account ID (WABA)')
                        ->helperText('Required to sync approved templates from Meta.'),
                    TextInput::make('access_token')
                        ->label('Replace access token')
                        ->password()
                        ->revealable()
                        ->placeholder('Leave blank to keep the saved token')
                        ->helperText('Permanent or long-lived system user token with whatsapp_business_messaging permission.')
                        ->extraInputAttributes([
                            'autocomplete' => 'new-password',
                            'data-1p-ignore' => 'true',
                            'data-lpignore' => 'true',
                        ])
                        ->columnSpanFull(),
                    TextInput::make('default_language')
                        ->label('Default template language')
                        ->default('en')
                        ->helperText('ISO code, e.g. en or en_US — must match the template in Meta.'),
                ])
                ->columns(2),
            Section::make('Meta webhook')
                ->description('Paste callback URL and verify token in Meta → WhatsApp → Configuration. Save app secret here so delivery updates are verified.')
                ->schema([
                    TextInput::make('verify_token')
                        ->label('Webhook verify token')
                        ->helperText('Choose any secret string — Meta will send it back during webhook verification.'),
                    TextInput::make('app_secret')
                        ->label('Replace app secret')
                        ->password()
                        ->revealable()
                        ->placeholder('Leave blank to keep the saved secret')
                        ->helperText('From Meta app → Basic settings. Required to verify webhook POST requests.'),
                    Placeholder::make('webhook_info')
                        ->label('')
                        ->content(fn (MetaWhatsAppSettingsService $settings): \Illuminate\Support\HtmlString => $settings->renderWebhookInfo())
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Recent Meta messages')
                ->description('Last 15 messages sent or received via Meta. Full history is under Meta WhatsApp → Message log.')
                ->schema([
                    Placeholder::make('recent_messages_table')
                        ->label('')
                        ->content(fn (MetaWhatsAppSettingsService $settings): \Illuminate\Support\HtmlString => $settings->renderRecentMessagesTable())
                        ->columnSpanFull(),
                ]),
            Section::make('Synced Meta templates')
                ->description('Approved templates from your WABA. Pal Digital template list is separate.')
                ->schema([
                    Placeholder::make('synced_templates_table')
                        ->label('')
                        ->content(fn (MetaWhatsAppSettingsService $settings): \Illuminate\Support\HtmlString => $settings->renderSyncedTemplatesTable())
                        ->columnSpanFull(),
                ]),
            Section::make('Test send')
                ->description('Send one approved template to your phone. Does not affect Pal Digital campaigns.')
                ->schema([
                    TextInput::make('test_phone')
                        ->label('Test mobile')
                        ->tel()
                        ->helperText('10-digit Indian mobile or 91XXXXXXXXXX.'),
                    Select::make('test_template_name')
                        ->label('Template')
                        ->options(fn (): array => MetaWhatsAppTemplate::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (MetaWhatsAppTemplate $template): array => [
                                $template->name => $template->name.' ('.$template->language.', '.$template->param_count.' params)',
                            ])
                            ->all())
                        ->searchable()
                        ->native(false)
                        ->nullable(),
                    TextInput::make('test_template_language')
                        ->label('Template language')
                        ->default('en'),
                    TextInput::make('test_template_param_1')
                        ->label('Parameter 1'),
                    TextInput::make('test_template_param_2')
                        ->label('Parameter 2'),
                    TextInput::make('test_template_param_3')
                        ->label('Parameter 3'),
                ])
                ->columns(2),
        ]);
    }

    public function save(MetaWhatsAppSettingsService $settings): void
    {
        $result = $settings->save($this->form->getState());

        if (! $result['ok']) {
            Notification::make()
                ->title('Could not save Meta WhatsApp settings')
                ->body($result['message'] ?? 'Check the credentials.')
                ->danger()
                ->send();

            return;
        }

        $this->refillForm($settings);

        Notification::make()
            ->title('Meta WhatsApp settings saved')
            ->body('Credentials saved. Pal Digital WhatsApp is unchanged.'.$settings->ignoredReplaceTokenNotice((bool) ($result['ignored_invalid_token_field'] ?? false)))
            ->success()
            ->send();
    }

    public function syncTemplates(
        MetaWhatsAppSettingsService $settings,
        MetaWhatsAppTemplateSyncService $sync,
    ): void {
        $saved = $settings->saveCredentials($this->form->getState());

        if (! $saved['ok']) {
            Notification::make()
                ->title('Could not sync templates')
                ->body($saved['message'] ?? 'Fix credentials first.')
                ->danger()
                ->send();

            return;
        }

        if (! $settings->hasStoredAccessToken()) {
            Notification::make()
                ->title('Could not sync templates')
                ->body('Save a Meta access token first.')
                ->danger()
                ->send();

            return;
        }

        $result = $sync->sync();
        $this->refillForm($settings);

        Notification::make()
            ->title($result['synced'].' Meta template(s) synced')
            ->body($result['message'].$settings->ignoredReplaceTokenNotice((bool) ($saved['ignored_invalid_token_field'] ?? false)))
            ->success($result['status'] === 'success')
            ->warning($result['status'] !== 'success')
            ->send();
    }

    public function testConnection(
        MetaWhatsAppSettingsService $settings,
        MetaWhatsAppService $meta,
    ): void {
        $saved = $settings->saveCredentials($this->form->getState());

        if (! $saved['ok']) {
            Notification::make()
                ->title('Connection check failed')
                ->body($saved['message'] ?? 'Fix credentials first.')
                ->danger()
                ->send();

            return;
        }

        $result = $meta->validateConnection();
        $this->refillForm($settings);

        Notification::make()
            ->title($result['status'] === 'success' ? 'Meta connection OK' : 'Connection check failed')
            ->body($result['message'].$settings->ignoredReplaceTokenNotice((bool) ($saved['ignored_invalid_token_field'] ?? false)))
            ->success($result['status'] === 'success')
            ->danger($result['status'] !== 'success')
            ->send();
    }

    public function sendTestMessage(
        MetaWhatsAppSettingsService $settings,
        MetaWhatsAppService $meta,
    ): void {
        $state = $this->form->getState();
        $saved = $settings->save($state);

        if (! $saved['ok']) {
            Notification::make()
                ->title('Test send failed')
                ->body($saved['message'] ?? 'Fix credentials first.')
                ->danger()
                ->send();

            return;
        }

        $templateName = trim((string) ($state['test_template_name'] ?? ''));
        $phone = trim((string) ($state['test_phone'] ?? ''));
        $language = trim((string) ($state['test_template_language'] ?? 'en'));

        if ($templateName === '' || $phone === '') {
            Notification::make()
                ->title('Test send failed')
                ->body('Choose a template and enter a test mobile number.')
                ->warning()
                ->send();

            return;
        }

        $template = MetaWhatsAppTemplate::query()
            ->where('name', $templateName)
            ->where('language', $language)
            ->first();

        $params = $settings->testTemplateParams();
        $result = $meta->sendTemplate(
            $phone,
            $templateName,
            $params,
            $language,
            (int) ($template?->param_count ?? count($params)),
        );

        $this->refillForm($settings);

        Notification::make()
            ->title($result['status'] === 'success' ? 'Test message sent' : 'Test send failed')
            ->body($result['status'] === 'success'
                ? 'Check WhatsApp on '.$phone.'. Message ID: '.($result['message_id'] ?? 'n/a')
                : (string) ($result['error'] ?? 'Unknown error'))
            ->success($result['status'] === 'success')
            ->danger($result['status'] !== 'success')
            ->send();
    }

    protected function refillForm(MetaWhatsAppSettingsService $settings): void
    {
        $this->form->fill($settings->getFormData());
        $this->data['access_token'] = '';
        $this->data['app_secret'] = '';
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
            ->id('metaWhatsAppSettingsForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('syncTemplates')
                        ->label('Sync templates')
                        ->icon('heroicon-o-arrow-path')
                        ->action('syncTemplates'),
                    Action::make('testConnection')
                        ->label('Test connection')
                        ->icon('heroicon-o-signal')
                        ->color('gray')
                        ->action('testConnection'),
                    Action::make('sendTestMessage')
                        ->label('Send test message')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('gray')
                        ->action('sendTestMessage'),
                    Action::make('save')
                        ->label('Save settings')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }
}
