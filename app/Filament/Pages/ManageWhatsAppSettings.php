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

    protected static bool $shouldRegisterNavigation = false;

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
            Placeholder::make('how_this_page_works')
                ->label('')
                ->content(new HtmlString(
                    '<p class="text-sm text-gray-600 dark:text-gray-300">'
                    .'<strong>WhatsApp setup</strong> is the master switch for the institute. This page is where you turn each message type on or off. '
                    .'Example: keep Setup on, turn <strong>Attendance to parents</strong> off, and leave fees and homework on.'
                    .'</p>'
                ))
                ->columnSpanFull(),
            Section::make('Live campaigns for automations')
                ->description(fn (): string => 'Pick a **live** campaign from '.CrmNavigation::whatsAppMenu('Live campaigns').'. Each campaign links to an approved Meta template.')
                ->schema([
                    Placeholder::make('live_campaigns_notice')
                        ->label('')
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderLiveCampaignsNotice())
                        ->columnSpanFull(),
                ]),
            Section::make('Parents — attendance')
                ->description('Turn this off if you want WhatsApp for fees or homework but not gate IN/OUT messages.')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->schema([
                    Placeholder::make('attendance_automation_guide')
                        ->label('')
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderAttendanceAutomationGuide())
                        ->columnSpanFull(),
                    Toggle::make('punch_autosend_enabled')
                        ->label('Send attendance messages to parents')
                        ->helperText('IN and OUT from the biometric machine and from the Attendance screen. Turn off to stop all parent attendance WhatsApp.')
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
            Section::make('Staff — attendance')
                ->description('Messages to the staff member’s own phone. Independent of parent attendance.')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    Placeholder::make('staff_punch_automation_guide')
                        ->hiddenLabel()
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderStaffPunchAutomationGuide())
                        ->columnSpanFull(),
                    Toggle::make('staff_punch_autosend_enabled')
                        ->label('Send attendance messages to staff')
                        ->helperText('Does not send to parents. Auto-out at night does not send.')
                        ->columnSpanFull(),
                    Select::make('staff_punch_in_autosend_live_campaign_id')
                        ->label('Staff biometric check-in (IN)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Map {{1}} staff.name, {{2}} attendance.time, {{3}} attendance.date, {{4}} institute.name.'),
                    Select::make('staff_punch_out_autosend_live_campaign_id')
                        ->label('Staff biometric check-out (OUT)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Same mapping as IN. Leave blank if you only want IN messages.'),
                ])
                ->columns(2),
            Section::make('Leads — after a call')
                ->description('Optional message after a connected outgoing call is logged.')
                ->collapsed()
                ->schema([
                    Toggle::make('postcall_autosend_enabled')
                        ->label('Send WhatsApp after a logged call'),
                    Select::make('postcall_autosend_live_campaign_id')
                        ->label('Live campaign')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false),
                ])
                ->columns(2),
            Section::make('Parents — fee reminders')
                ->description('Daily overdue installment messages. Turn on here even if attendance is off.')
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Placeholder::make('fee_reminder_template_guide')
                        ->hiddenLabel()
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderFeeReminderTemplateGuide())
                        ->columnSpanFull(),
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
                        ->helperText('Map {{1}} institute.name, {{2}} student.name, {{3}} fee.pending_amount, {{4}} fee.due_date — then Go live.'),
                ])
                ->columns(2),
            Section::make('Parents — homework not done')
                ->description('When staff submit Not Done on Homework check. Share-homework from class is a separate Send button, not this switch.')
                ->icon(Heroicon::OutlinedBookOpen)
                ->schema([
                    Placeholder::make('homework_not_done_template_guide')
                        ->hiddenLabel()
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderHomeworkNotDoneTemplateGuide())
                        ->columnSpanFull(),
                    Toggle::make('homework_not_done_autosend_enabled')
                        ->label('Send WhatsApp when homework is Not Done')
                        ->helperText('Never sends when status is Done.')
                        ->columnSpanFull(),
                    Select::make('homework_not_done_live_campaign_id')
                        ->label('Homework not done live campaign')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->liveCampaignOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Map student.name, homework.class_section, homework.subject, homework.topic, institute.name.'),
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
