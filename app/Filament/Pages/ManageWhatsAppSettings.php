<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Services\WhatsAppProviderResolver;
use App\Services\WhatsAppSettingsService;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
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

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::whatsAppAutomations();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::whatsAppAutomations();
    }

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
                ->visible(fn (): bool => app(WhatsAppProviderResolver::class)->isMetaActive()),
            Section::make('Live campaigns for automations')
                ->description(fn (): string => 'Pick a **live** campaign from '.CrmNavigation::whatsAppMenu('Live campaigns').'. Each campaign links to an approved Meta template.')
                ->schema([
                    Placeholder::make('live_campaigns_notice')
                        ->label('')
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderLiveCampaignsNotice())
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
                    Select::make('punch_in_autosend_live_campaign_id')
                        ->label('Biometric check-in (IN)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('When student punches IN at the gate/device.'),
                    Select::make('punch_out_autosend_live_campaign_id')
                        ->label('Biometric check-out (OUT)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('When student punches OUT at the device.'),
                    Placeholder::make('manual_templates_heading')
                        ->label('')
                        ->content(new HtmlString('<p class="mt-2 text-sm font-bold text-gray-950 dark:text-white">From staff on Attendance screen</p><p class="mt-0.5 text-xs text-gray-500">Manual IN, Manual OUT, or batch IN/OUT buttons.</p>'))
                        ->columnSpanFull(),
                    Select::make('punch_manual_in_autosend_live_campaign_id')
                        ->label('Manual check-in (IN)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Staff marks IN. Leave blank to reuse Biometric IN campaign.'),
                    Select::make('punch_manual_out_autosend_live_campaign_id')
                        ->label('Manual check-out (OUT)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Staff marks OUT. Leave blank to reuse Biometric OUT campaign.'),
                    Toggle::make('attendance_autosend_enabled')
                        ->label('Legacy: batch-save template (optional)')
                        ->helperText('Old roll-call Present flow only. Manual IN/OUT on Attendance uses the four punch templates above.')
                        ->columnSpanFull(),
                    Select::make('attendance_autosend_live_campaign_id')
                        ->label('Fallback attendance campaign')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Used when a specific IN/OUT campaign is left blank.'),
                ])
                ->columns(2),
            Section::make('Post-call auto message')
                ->description('After a connected outgoing call is logged, queue a WhatsApp using the selected template.')
                ->collapsed()
                ->schema([
                    Toggle::make('postcall_autosend_enabled')
                        ->label('Enable post-call WhatsApp'),
                    Select::make('postcall_autosend_live_campaign_id')
                        ->label('Live campaign')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false),
                ])
                ->columns(2),
            Section::make('Fee reminders')
                ->description('Daily WhatsApp to parents with overdue installments. Uses an approved Meta template only — create and approve `fee_reminder` (or similar) under WhatsApp → Templates, then link a live campaign.')
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Toggle::make('fee_reminder_autosend_enabled')
                        ->label('Send daily fee reminders')
                        ->helperText('Runs at 09:00 via scheduler (`crm:send-fee-reminders`). Same student is not reminded again within the cooldown in config/fees.php.')
                        ->columnSpanFull(),
                    Select::make('fee_reminder_live_campaign_id')
                        ->label('Fee reminder live campaign')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Map template variables: student name, pending amount, due date, institute name.'),
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
                ->body($result['message'] ?? 'Check automation settings and try again.')
                ->danger()
                ->send();

            return;
        }

        $this->form->fill($settings->getFormData());

        Notification::make()
            ->title('Automations saved')
            ->body('Automation and campaign settings are saved. Sends route through Meta Cloud API.')
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
            ->id('whatsappSettingsForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save settings')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }
}
