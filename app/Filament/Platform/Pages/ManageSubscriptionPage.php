<?php

namespace App\Filament\Platform\Pages;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Services\LicenseService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSubscriptionPage extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'License & features';

    protected static ?string $title = 'Client license';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public bool $signatureValid = true;

    public bool $licenseActive = true;

    public ?int $daysRemaining = null;

    public function mount(LicenseService $license): void
    {
        $this->refreshStatus($license);

        $current = $license->current();

        $this->form->fill([
            'features' => $current['features'],
            'expires_at' => optional($license->expiresAt())?->toDateString(),
            'annual_price_inr' => $current['annual_price_inr'],
            'client_name' => $current['client_name'],
            'notes' => $current['notes'],
            'max_students' => $current['max_students'],
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client')
                ->columns(2)
                ->schema([
                    TextInput::make('client_name')
                        ->label('Client / institute name')
                        ->maxLength(255),
                    TextInput::make('annual_price_inr')
                        ->label('Annual price (INR)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Internal reference only — not shown to school staff.'),
                    DatePicker::make('expires_at')
                        ->label('License valid until')
                        ->required()
                        ->native(false),
                    TextInput::make('max_students')
                        ->label('Student cap (optional)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Leave empty for unlimited.'),
                ]),
            Section::make('Enabled modules')
                ->description('Core student, course, batch, and staff management is always included. Toggle optional modules for this school.')
                ->schema([
                    CheckboxList::make('features')
                        ->label('Modules')
                        ->options(LicenseFeature::options())
                        ->descriptions(collect(LicenseFeature::cases())
                            ->mapWithKeys(fn (LicenseFeature $feature): array => [$feature->value => $feature->description()])
                            ->all())
                        ->columns(2)
                        ->required(),
                ]),
            Section::make('Internal notes')
                ->schema([
                    Textarea::make('notes')
                        ->rows(4)
                        ->maxLength(2000)
                        ->helperText('Renewal history, invoice refs, or support notes.'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.platform.pages.partials.license-status')
                ->viewData(fn (): array => [
                    'signatureValid' => $this->signatureValid,
                    'licenseActive' => $this->licenseActive,
                    'daysRemaining' => $this->daysRemaining,
                ]),
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('manageSubscriptionForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save signed license')
                        ->submit('save')
                        ->icon(Heroicon::OutlinedCheckCircle),
                ]),
            ]);
    }

    public function save(LicenseService $license): void
    {
        $data = $this->form->getState();
        $data['plan'] = LicensePlan::Custom->value;

        $license->save($data);
        $this->refreshStatus($license);

        Notification::make()
            ->title('License updated')
            ->body('Signed license saved. School admin menus will reflect enabled modules.')
            ->success()
            ->send();
    }

    private function refreshStatus(LicenseService $license): void
    {
        $this->signatureValid = $license->isSignatureValid();
        $this->licenseActive = $license->isActive();
        $this->daysRemaining = $license->daysRemaining();
    }
}
