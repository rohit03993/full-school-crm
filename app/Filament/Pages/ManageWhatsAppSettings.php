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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
                    .'<strong>WhatsApp setup</strong> is the master switch for the institute. Use the tabs below — only one message type is shown at a time. '
                    .'<strong>Student attendance</strong> = student IN/OUT, WhatsApp to parents. '
                    .'<strong>Staff attendance</strong> = staff punch, WhatsApp to the staff phone. '
                    .'<strong>Homework</strong> = share templates (staff click Send) and optional Not Done alerts. '
                    .'Pick an approved template on each tab. Live API campaigns are only for external POST / API key, not these automations.'
                    .'</p>'
                ))
                ->columnSpanFull(),
            Placeholder::make('live_campaigns_notice')
                ->label('')
                ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderLiveCampaignsNotice())
                ->columnSpanFull(),
            Tabs::make('Automations')
                ->persistTabInQueryString('automation')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Student attendance')
                        ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->schema([
            Section::make('Student attendance — WhatsApp to parents')
                ->description('These messages go to the parent mobile when a student punches IN or OUT. This is not staff attendance — that is the next tab.')
                ->schema([
                    Placeholder::make('attendance_automation_guide')
                        ->label('')
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderAttendanceAutomationGuide())
                        ->columnSpanFull(),
                    Toggle::make('punch_autosend_enabled')
                        ->label('Send student IN/OUT WhatsApp to parents')
                        ->helperText('Student biometric punch or teacher marking the student IN/OUT on Attendance. Turn off to stop parent attendance WhatsApp only.')
                        ->columnSpanFull(),
                    Placeholder::make('machine_templates_heading')
                        ->label('')
                        ->content(new HtmlString('<p class="text-sm font-bold text-gray-950 dark:text-white">From biometric device (punch_logs)</p><p class="mt-0.5 text-xs text-gray-500">EasyTimePro writes to MySQL — CRM reads automatically.</p>'))
                        ->columnSpanFull(),
                    Select::make('punch_in_autosend_live_campaign_id')
                        ->label('Biometric check-in (IN)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('When student punches IN at the gate/device.'),
                    Select::make('punch_out_autosend_live_campaign_id')
                        ->label('Biometric check-out (OUT)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('When student punches OUT at the device.'),
                    Placeholder::make('manual_templates_heading')
                        ->label('')
                        ->content(new HtmlString('<p class="mt-2 text-sm font-bold text-gray-950 dark:text-white">Teacher marks the student on Attendance</p><p class="mt-0.5 text-xs text-gray-500">Manual IN, Manual OUT, or batch IN/OUT — still a student event; WhatsApp goes to the parent.</p>'))
                        ->columnSpanFull(),
                    Select::make('punch_manual_in_autosend_live_campaign_id')
                        ->label('Manual check-in (IN)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Teacher marks the student IN. Leave blank to reuse the Biometric IN template.'),
                    Select::make('punch_manual_out_autosend_live_campaign_id')
                        ->label('Manual check-out (OUT)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Teacher marks the student OUT. Leave blank to reuse the Biometric OUT template.'),
                    Toggle::make('attendance_autosend_enabled')
                        ->label('Legacy: batch-save template (optional)')
                        ->helperText('Old roll-call Present flow only. Manual IN/OUT on Attendance uses the four punch templates above.')
                        ->columnSpanFull(),
                    Select::make('attendance_autosend_live_campaign_id')
                        ->label('Fallback attendance template')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Used when a specific IN/OUT template is left blank.'),
                ])
                ->columns(2),
                        ]),
                    Tab::make('Staff attendance')
                        ->icon(Heroicon::OutlinedUserGroup)
                        ->schema([
            Section::make('Staff attendance — WhatsApp to the staff phone')
                ->description('When a staff member punches IN or OUT, the message goes to that staff member’s own mobile. Parents and students are not messaged from this tab.')
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
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Map {{1}} staff.name, {{2}} attendance.time, {{3}} attendance.date, {{4}} institute.name.'),
                    Select::make('staff_punch_out_autosend_live_campaign_id')
                        ->label('Staff biometric check-out (OUT)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Same mapping as IN. Leave blank if you only want IN messages.'),
                ])
                ->columns(2),
                        ]),
                    Tab::make('After a call')
                        ->icon(Heroicon::OutlinedPhone)
                        ->schema([
            Section::make('Leads — after a call')
                ->description('Optional message after a connected outgoing call is logged.')
                ->schema([
                    Toggle::make('postcall_autosend_enabled')
                        ->label('Send WhatsApp after a logged call'),
                    Select::make('postcall_autosend_live_campaign_id')
                        ->label('Template')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false),
                ])
                ->columns(2),
                        ]),
                    Tab::make('Fee reminders')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->schema([
            Section::make('Parents — fee reminders')
                ->description('Automatic WhatsApp on the parent mobile. Upcoming = N days before due. Due today = on the due date. Overdue = after due date. Staff can also send from the student Fees tab.')
                ->schema([
                    Placeholder::make('fee_reminder_template_guide')
                        ->hiddenLabel()
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderFeeReminderTemplateGuide())
                        ->columnSpanFull(),
                    Toggle::make('fee_reminder_autosend_enabled')
                        ->label('Send automatic fee reminders')
                        ->helperText('Master switch for the three stages below. Scheduler runs hourly and sends once per day at the time you set.')
                        ->columnSpanFull(),
                    TextInput::make('fee_reminder_days_before')
                        ->label('Days before due date')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(14)
                        ->helperText('Example: 2 = 48 hours before. Due 27 Aug → send on 25 Aug.'),
                    TextInput::make('fee_reminder_send_time')
                        ->label('Send time')
                        ->placeholder('10:00')
                        ->helperText('24-hour clock, institute timezone (e.g. 10:00).'),
                    Toggle::make('fee_reminder_upcoming_enabled')
                        ->label('Upcoming (before due date)')
                        ->columnSpanFull(),
                    Select::make('fee_reminder_upcoming_live_campaign_id')
                        ->label('Upcoming template')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Template fee_reminder_upcoming. Map institute, student, amount, due date.'),
                    Toggle::make('fee_reminder_due_enabled')
                        ->label('Due today')
                        ->columnSpanFull(),
                    Select::make('fee_reminder_due_live_campaign_id')
                        ->label('Due-today template')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Template fee_reminder_due.'),
                    Toggle::make('fee_reminder_overdue_enabled')
                        ->label('Overdue')
                        ->columnSpanFull(),
                    Select::make('fee_reminder_overdue_live_campaign_id')
                        ->label('Overdue template')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptions())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Template fee_reminder_overdue (or fee_reminder). Same student is not reminded again within the cooldown in config/fees.php.'),
                ])
                ->columns(2),
                        ]),
                    Tab::make('Homework')
                        ->icon(Heroicon::OutlinedBookOpen)
                        ->schema([
            Section::make('Parents — share homework')
                ->description('Pick templates here. Staff still click Send on Homework Review (combined) or when uploading one subject — messages do not go out until they confirm.')
                ->schema([
                    Placeholder::make('homework_share_template_guide')
                        ->hiddenLabel()
                        ->content(fn (WhatsAppSettingsService $settings): HtmlString => $settings->renderHomeworkShareTemplateGuide())
                        ->columnSpanFull(),
                    Select::make('homework_combined_live_campaign_id')
                        ->label('Combined daily homework (all subjects)')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptionsForParamCount(4))
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Used on Homework Review → Send to parents. Template homework_combined (4 params).'),
                    Select::make('homework_share_live_campaign_id')
                        ->label('Single-subject homework share')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptionsForParamCount(4))
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Used when uploading one homework with Send WhatsApp on. Template homework_api / homework_update (4 params).'),
                ])
                ->columns(2),
            Section::make('Parents — homework not done')
                ->description('Automatic when staff submit Not Done on Homework check. Separate from share homework above.')
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
                        ->label('Homework not done template')
                        ->options(fn (WhatsAppSettingsService $settings): array => $settings->templateOptionsForParamCount(5))
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->helperText('Template homework_not_done (5 params). Map student.name, homework.class_section, homework.subject, homework.topic, institute.name.'),
                ])
                ->columns(2),
                        ]),
                    Tab::make('Sending batches')
                        ->icon(Heroicon::OutlinedRectangleStack)
                        ->schema([
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
                        ]),
                ]),
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
