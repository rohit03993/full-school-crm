<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\PushSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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

class ManagePushNotificationsPage extends Page
{
    use RequiresCrmPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::SettingsManage;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::pushNotifications();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::pushNotifications();
    }

    protected static ?int $navigationSort = 56;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

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
        $this->form->fill(PushSettings::formDefaults());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Server status')
                ->description('VAPID keys in .env are required before any device can receive lock-screen alerts.')
                ->schema([
                    Placeholder::make('vapid_status')
                        ->label('VAPID keys')
                        ->content(fn (): HtmlString => ($this->data['vapid_configured'] ?? false)
                            ? new HtmlString('<span class="font-semibold text-emerald-700 dark:text-emerald-300">Configured</span> — push can send when users allow notifications.')
                            : new HtmlString('<span class="font-semibold text-amber-700 dark:text-amber-300">Not configured</span> — run <code class="text-xs">php artisan crm:webpush-vapid</code> and add the printed lines to <code class="text-xs">.env</code>, then <code class="text-xs">php artisan config:clear</code>.')),
                ]),
            Section::make('Master switch')
                ->schema([
                    Toggle::make('enabled')
                        ->label('Enable PWA push notifications')
                        ->helperText('Turns all push types below off when disabled. Users must still install the app and Allow notifications.')
                        ->live(),
                ]),
            Section::make('Staff / admin')
                ->description('Sent to the assigned staff member’s installed app (not every admin).')
                ->schema([
                    Toggle::make('followup_digest_enabled')
                        ->label('Morning follow-up digest')
                        ->helperText('Daily 08:30 — staff who have due follow-ups / call follow-ups.'),
                    Toggle::make('lead_assigned_enabled')
                        ->label('Lead assigned for calling'),
                    Toggle::make('visit_assigned_enabled')
                        ->label('Campus meeting / visit assigned'),
                    Toggle::make('case_assigned_enabled')
                        ->label('Support case assigned'),
                ])
                ->visible(fn (callable $get): bool => (bool) $get('enabled')),
            Section::make('Parents / students (portal)')
                ->description('Sent to devices that allowed notifications while logged into the student portal.')
                ->schema([
                    Toggle::make('fee_reminders_enabled')
                        ->label('Fee reminders')
                        ->helperText('Same eligibility list as daily fee reminders.'),
                    Toggle::make('attendance_enabled')
                        ->label('Attendance IN / OUT')
                        ->helperText('After each successful check-in or check-out (manual or biometric).'),
                    Toggle::make('homework_enabled')
                        ->label('New homework published'),
                    Toggle::make('marks_published_enabled')
                        ->label('Exam results published'),
                    Toggle::make('case_update_enabled')
                        ->label('Case call / update logged')
                        ->helperText('When staff logs a call on an open support case.'),
                ])
                ->visible(fn (callable $get): bool => (bool) $get('enabled')),
        ]);
    }

    public function save(): void
    {
        PushSettings::saveFormData($this->form->getState());

        Notification::make()
            ->title('Push notification settings saved')
            ->success()
            ->send();

        $this->form->fill(PushSettings::formDefaults());
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
            ->id('pushSettingsForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save push settings')
                        ->submit('save'),
                ]),
            ]);
    }
}
