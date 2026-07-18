<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\FaceVerify\FaceVerifyPlatformService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class ManageFacePlatformPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 57;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $connectionStatus = [];

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::facePlatform();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::facePlatform();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Connect this CRM to the shared Face Platform. Paste the client code from Face admin, then enter the device number in the APK.';
    }

    public function mount(FaceVerifyPlatformService $platform): void
    {
        $this->connectionStatus = $platform->status();
        $this->form->fill([
            'face_api_url' => $this->connectionStatus['api_url'] ?: 'https://face.taskbook.co.in',
            'client_code' => $this->connectionStatus['client_code'] ?: '',
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Connect with client code')
                ->description('Vendor creates this school on face.taskbook.co.in (or your Face host) and gives you a client code.')
                ->schema([
                    TextInput::make('face_api_url')
                        ->label('Face Platform URL')
                        ->placeholder('https://face.taskbook.co.in')
                        ->required()
                        ->url()
                        ->helperText('Shared Face website for all schools — not this CRM URL.'),
                    TextInput::make('client_code')
                        ->label('Client code')
                        ->placeholder('TB-7K2M9Q')
                        ->required()
                        ->helperText('From Face Platform → Add client.'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.face-platform-connect')
                ->viewData(fn (): array => [
                    'status' => $this->connectionStatus,
                    'biometricUrl' => ManageAttendanceBiometricPage::getUrl(),
                ]),
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('facePlatformForm')
            ->livewireSubmitHandler('connect')
            ->footer([
                Actions::make([
                    Action::make('connect')
                        ->label('Connect')
                        ->submit('connect'),
                    Action::make('disconnect')
                        ->label('Disconnect')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => (bool) ($this->connectionStatus['connected'] ?? false))
                        ->action('disconnect'),
                ]),
            ]);
    }

    public function connect(FaceVerifyPlatformService $platform): void
    {
        $state = $this->form->getState();

        try {
            $result = $platform->connect(
                (string) ($state['face_api_url'] ?? ''),
                (string) ($state['client_code'] ?? ''),
            );
            $this->connectionStatus = $result['status'];
            $this->form->fill([
                'face_api_url' => $this->connectionStatus['api_url'] ?: ($state['face_api_url'] ?? ''),
                'client_code' => $this->connectionStatus['client_code'] ?: ($state['client_code'] ?? ''),
            ]);
            Notification::make()
                ->title('Connected')
                ->body($result['message'])
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Connect failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function disconnect(FaceVerifyPlatformService $platform): void
    {
        $platform->disconnect();
        $this->connectionStatus = $platform->status();
        $this->form->fill([
            'face_api_url' => 'https://face.taskbook.co.in',
            'client_code' => '',
        ]);
        Notification::make()
            ->title('Disconnected')
            ->success()
            ->send();
    }
}
