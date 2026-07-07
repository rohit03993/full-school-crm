<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Support\CrmNavigation;
use App\Support\FeeSettings;
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

    protected static ?string $navigationLabel = 'Fee Settings';

    protected static ?string $title = 'Fee settings';

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
            Section::make('Late fees (read-only)')
                ->description('Configured in config/fees.php. Applied by crm:process-late-fees daily.')
                ->schema([
                    Toggle::make('late_fee_enabled')->label('Late fees enabled')->disabled(),
                    TextInput::make('late_fee_grace_days')->label('Grace days')->disabled(),
                    TextInput::make('late_fee_daily_rate')
                        ->label('Daily rate')
                        ->disabled()
                        ->helperText('0.0015 = 0.15% of pending installment per day after grace.'),
                ]),
        ]);
    }

    public function save(): void
    {
        FeeSettings::saveFormData($this->form->getState());

        Notification::make()
            ->title('Fee settings saved')
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
