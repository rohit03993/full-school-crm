<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeeSettings;
use App\Services\OnlineAllowanceGstService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ManageFeeSettings extends Page
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::SettingsManage;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::feeSettings();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::feeSettings();
    }

    protected static ?int $navigationSort = 55;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

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
        $this->form->fill(FeeSettings::formDefaults());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Coaching: cash vs online agreement')
                ->description('Record how much of the net tuition fee the student agreed to pay in cash vs online. If they pay more tuition online than agreed, GST is charged on the excess only.')
                ->schema([
                    Toggle::make('online_allowance_gst_enabled')
                        ->label('Enable cash / online split and GST on online overage')
                        ->helperText('Turning this off clears all cash/online splits and removes unpaid GST penalty charges.')
                        ->live(),
                    TextInput::make('gst_penalty_percentage')
                        ->label('GST % on online excess')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->suffix('%')
                        ->visible(fn (callable $get): bool => (bool) $get('online_allowance_gst_enabled')),
                ]),
            Section::make('Late fees on overdue installments')
                ->description('When enabled, the daily job creates late fee misc charges on the student Fees tab after the grace period. Grace days and daily rate are set in config/fees.php or .env.')
                ->schema([
                    Toggle::make('late_fee_enabled')
                        ->label('Enable late fees')
                        ->helperText('Turn off to stop new late fee penalties. Existing unpaid late fees remain until paid or waived.'),
                    TextInput::make('late_fee_grace_days')
                        ->label('Grace days (read-only)')
                        ->disabled()
                        ->helperText('Days after due date before penalties start. Set FEE_LATE_FEE_GRACE_DAYS in .env.'),
                    TextInput::make('late_fee_daily_rate')
                        ->label('Daily rate (read-only)')
                        ->disabled()
                        ->helperText('0.0015 = 0.15% of pending installment per day after grace. Set FEE_LATE_FEE_DAILY_RATE in .env.'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $wasGstEnabled = FeeSettings::onlineAllowanceGstEnabled();
        $willGstEnable = (bool) ($state['online_allowance_gst_enabled'] ?? false);

        FeeSettings::saveFormData($state);

        $notification = Notification::make()
            ->title('Fee settings saved')
            ->success();

        if ($wasGstEnabled && ! $willGstEnable) {
            $cleanup = app(OnlineAllowanceGstService::class)->cleanupWhenDisabled(Auth::user());

            $notification->body(
                'Cleared cash/online split on '.$cleanup['splits_cleared'].' fee record(s)'
                .' and removed '.$cleanup['gst_charges_removed'].' unpaid GST charge(s).',
            );
        }

        $notification->send();
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
            ->id('feeSettingsForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save fee settings')
                        ->submit('save'),
                ]),
            ]);
    }
}
