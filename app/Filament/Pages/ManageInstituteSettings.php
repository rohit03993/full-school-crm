<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Pages\ManageSiteContent as ManageSiteContentPage;
use App\Services\InstituteSettingsService;
use App\Services\SiteImageService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ManageInstituteSettings extends Page
{
    use CanUseDatabaseTransactions;
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::SettingsManage;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Institute Settings';

    protected static ?string $title = 'Institute Settings';

    protected static ?int $navigationSort = 50;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(InstituteSettingsService $settings): void
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
            CrmHint::placeholder('setup.institute_settings'),
            Section::make('Institute identity')
                ->description('Name and contact details are shared with the public website. Edit them under Website → Site Content.')
                ->schema([
                    Placeholder::make('identity_preview')
                        ->label('Current identity')
                        ->content(fn (): HtmlString => new HtmlString(
                            '<div class="space-y-1 text-sm text-gray-600 dark:text-gray-300">'
                            .'<p><span class="font-semibold text-gray-950 dark:text-white">'.e($this->data['name'] ?? '').'</span></p>'
                            .'<p>'.e($this->data['tagline'] ?? '').'</p>'
                            .'<p>'.e($this->data['phone'] ?? '').' · '.e($this->data['email'] ?? '').'</p>'
                            .'<p>'.e($this->data['address'] ?? '').'</p>'
                            .'</div>'
                        ))
                        ->columnSpanFull(),
                    Placeholder::make('site_content_link')
                        ->label('')
                        ->content(fn (): HtmlString => new HtmlString(
                            '<a href="'.e(ManageSiteContentPage::getUrl()).'" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open Site Content →</a>'
                        ))
                        ->columnSpanFull(),
                ]),
            Section::make('Receipt & PDF branding')
                ->description('Logo and footer appear on fee receipts, ID cards, and exported PDF reports.')
                ->schema([
                    FileUpload::make('receipt_logo')
                        ->label('Receipt / PDF logo')
                        ->image()
                        ->maxSize(SiteImageService::MAX_KILOBYTES)
                        ->imageEditor()
                        ->imageEditorAspectRatioOptions([
                            null => 'Free crop',
                            '16:9' => '16:9',
                            '4:3' => '4:3',
                            '1:1' => 'Square',
                        ])
                        ->disk(SiteImageService::DISK)
                        ->directory('crm/branding')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->helperText('Optional. Falls back to the website logo. Max 350 KB. Old file is removed on save.'),
                    TextInput::make('receipt_header')
                        ->label('Receipt header line')
                        ->maxLength(255)
                        ->placeholder('e.g. Authorized Training Centre')
                        ->helperText('Optional extra line printed below the institute name on receipts.'),
                    Textarea::make('receipt_footer')
                        ->label('Receipt footer')
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText('Legal note printed at the bottom of every fee receipt.'),
                ])
                ->columns(2),
            Section::make('Student portal login')
                ->description('All students sign in at /portal/login with mobile + this password. They can change their password after logging in.')
                ->schema([
                    TextInput::make('portal_shared_password')
                        ->label('Default student portal password')
                        ->password()
                        ->revealable()
                        ->helperText('Leave blank to keep the current password. New students receive this password when enrolled. Changing it does not reset passwords students already changed.'),
                ])
                ->columns(1),
        ]);
    }

    public function save(InstituteSettingsService $settings): void
    {
        $settings->save($this->form->getState());

        $this->form->fill($settings->getFormData());

        Notification::make()
            ->title('Institute settings saved')
            ->body('Receipts, ID cards, and PDF exports will use the updated branding.')
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
            ->id('instituteSettingsForm')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save Institute Settings')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }
}
