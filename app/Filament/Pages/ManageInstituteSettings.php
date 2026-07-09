<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Pages\ManageSiteContent as ManageSiteContentPage;
use App\Services\InstituteSettingsService;
use App\Services\SiteImageService;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class ManageInstituteSettings extends Page
{
    use CanUseDatabaseTransactions;
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::SettingsManage;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::instituteSettings();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::instituteSettings();
    }

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
            Section::make('Marksheet & report card PDF')
                ->description('Controls fields shown on exam marksheets and consolidated report cards.')
                ->schema([
                    TextInput::make('marksheet_title')
                        ->label('Document title')
                        ->maxLength(120)
                        ->default('Statement of Marks'),
                    TextInput::make('marksheet_signature_title')
                        ->label('Signature line title')
                        ->maxLength(120)
                        ->placeholder('e.g. Principal / Controller of Examination'),
                    TextInput::make('marksheet_signature_name')
                        ->label('Signatory name (optional)')
                        ->maxLength(120)
                        ->placeholder('Printed below signature line'),
                    Textarea::make('marksheet_footer_note')
                        ->label('Footer note')
                        ->rows(3)
                        ->columnSpanFull(),
                    Toggle::make('marksheet_show_rank')
                        ->label('Show class rank')
                        ->default(true),
                    Toggle::make('marksheet_show_attendance')
                        ->label('Show attendance %')
                        ->default(true),
                    Toggle::make('marksheet_show_subject_remarks')
                        ->label('Show subject remarks column')
                        ->default(false),
                    Toggle::make('marksheet_show_principal_remarks')
                        ->label('Show principal remarks block')
                        ->default(true),
                    TextInput::make('marksheet_division_first')
                        ->label('First division from %')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(55),
                    TextInput::make('marksheet_division_second')
                        ->label('Second division from %')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(48),
                    TextInput::make('marksheet_division_pass')
                        ->label('Pass from %')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(40),
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
