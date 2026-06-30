<?php

namespace App\Filament\Pages;

use App\Services\StaffAccountService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Validation\ValidationException;

class MyAccountPage extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'My account';

    protected static ?string $title = 'My account';

    protected static ?string $slug = 'my-account';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return CrmAccess::hasPanelAccess(Auth::user());
    }

    public function mount(): void
    {
        $user = Auth::user();

        $this->form->fill([
            'name' => $user?->name,
            'mobile' => $user?->mobile,
            'current_password' => '',
            'new_password' => '',
            'new_password_confirmation' => '',
        ]);
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('account.my');
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Login details')
                ->description('You sign in at /admin with your mobile number and password. Changing mobile takes effect on your next login.')
                ->schema([
                    TextInput::make('name')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Ask a Super Admin to change your display name under Administration → Staff.'),
                    TextInput::make('mobile')
                        ->label('Mobile number')
                        ->tel()
                        ->required()
                        ->maxLength(14)
                        ->placeholder('10-digit mobile or +91…')
                        ->helperText('Must be unique — not used by any other staff login.'),
                    TextInput::make('current_password')
                        ->label('Current password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->helperText('Required to save any changes.'),
                    TextInput::make('new_password')
                        ->label('New password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->same('new_password_confirmation')
                        ->helperText('Leave blank to keep your current password.'),
                    TextInput::make('new_password_confirmation')
                        ->label('Confirm new password')
                        ->password()
                        ->revealable(),
                ])
                ->columns(1),
        ]);
    }

    public function save(StaffAccountService $accounts): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        try {
            $accounts->updateOwnAccount($user, $this->form->getState());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError('data.'.$field, $message);
                }
            }

            Notification::make()
                ->title('Could not update account')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        }

        $user = $user->fresh();

        $this->form->fill([
            'name' => $user->name,
            'mobile' => $user->mobile,
            'current_password' => '',
            'new_password' => '',
            'new_password_confirmation' => '',
        ]);

        Notification::make()
            ->title('Account updated')
            ->body('Your login details were saved. Use the new mobile and password next time you sign in.')
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
            ->id('myAccountForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save changes')
                        ->submit('save')
                        ->icon(Heroicon::OutlinedCheckCircle),
                ]),
            ]);
    }
}
