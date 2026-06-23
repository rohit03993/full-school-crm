<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\SiteContentService;
use App\Services\SiteImageService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ManageSiteContent extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Site Content';

    protected static ?string $title = 'Website Content';

    protected static ?int $navigationSort = 10;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_WEBSITE;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('setup.site_content');
    }

    public function mount(SiteContentService $siteContent): void
    {
        $this->form->fill($siteContent->getFormData());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CrmHint::placeholder('setup.site_content'),
            Tabs::make('Site Content')
                ->tabs([
                    Tab::make('Branding')
                        ->icon(Heroicon::OutlinedPhoto)
                        ->schema([
                            Section::make('Logo & Favicon')
                                ->description('Max 350 KB per image. Use the editor to crop after upload.')
                                ->schema([
                                    $this->imageUpload('logo', 'Institute Logo', 'site/logo'),
                                    $this->imageUpload('favicon', 'Favicon', 'site/favicon')
                                        ->imageEditorAspectRatioOptions(['1:1' => 'Square']),
                                ])
                                ->columns(2),
                            Section::make('Institute Name')
                                ->schema([
                                    TextInput::make('name')->required()->maxLength(255),
                                    TextInput::make('tagline')->required()->maxLength(255),
                                    TextInput::make('established')->label('Established Year')->maxLength(10),
                                    TextInput::make('number_prefix')
                                        ->label('Record ID prefix')
                                        ->maxLength(12)
                                        ->placeholder('CRM')
                                        ->helperText(CrmHint::field('record_prefix')),
                                ]),
                        ]),
                    Tab::make('Contact')
                        ->icon(Heroicon::OutlinedPhone)
                        ->schema([
                            TextInput::make('phone')->tel()->required(),
                            TextInput::make('whatsapp')->label('WhatsApp Number (digits only)'),
                            TextInput::make('email')->email()->required(),
                            Textarea::make('address')->rows(2)->columnSpanFull(),
                            TextInput::make('city'),
                            TextInput::make('hours')->label('Office Hours'),
                        ])
                        ->columns(2),
                    Tab::make('Hero')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->schema([
                            TextInput::make('hero_title')->label('Hero Title')->required()->columnSpanFull(),
                            Textarea::make('hero_subtitle')->label('Hero Subtitle')->rows(3)->columnSpanFull(),
                            Section::make('Hero Images')
                                ->schema([
                                    $this->imageUpload('hero_main_image', 'Main Background', 'site/hero'),
                                    $this->imageUpload('hero_accent_one', 'Accent Image 1', 'site/hero'),
                                    $this->imageUpload('hero_accent_two', 'Accent Image 2', 'site/hero'),
                                ])
                                ->columns(3),
                        ]),
                    Tab::make('About')
                        ->icon(Heroicon::OutlinedInformationCircle)
                        ->schema([
                            Textarea::make('about')->rows(6)->columnSpanFull(),
                            $this->imageUpload('about_image', 'About Section Image', 'site/about'),
                        ]),
                    Tab::make('Statistics')
                        ->icon(Heroicon::OutlinedChartBar)
                        ->schema([
                            Repeater::make('highlights')
                                ->label('Homepage Stats')
                                ->schema([
                                    TextInput::make('value')->required()->maxLength(20),
                                    TextInput::make('label')->required()->maxLength(100),
                                ])
                                ->columns(2)
                                ->defaultItems(4)
                                ->minItems(1)
                                ->maxItems(6)
                                ->reorderable()
                                ->columnSpanFull(),
                        ]),
                    Tab::make('Home Page')
                        ->icon(Heroicon::OutlinedHomeModern)
                        ->schema([
                            Section::make('About section')
                                ->schema([
                                    TextInput::make('home_about_eyebrow')->label('Eyebrow label')->maxLength(80),
                                    TextInput::make('home_about_title')->label('Heading')->maxLength(255)->columnSpanFull(),
                                    Repeater::make('home_about_points')
                                        ->label('Bullet points')
                                        ->schema([
                                            TextInput::make('text')->required()->maxLength(255),
                                        ])
                                        ->defaultItems(3)
                                        ->columnSpanFull(),
                                    TextInput::make('home_about_cta')->label('Link text')->maxLength(120),
                                ])
                                ->columns(2),
                            Section::make('Courses section')
                                ->schema([
                                    Toggle::make('home_show_courses_section')
                                        ->label('Show programmes section on homepage')
                                        ->helperText('Individual courses still need “Show on public website” enabled under Academics → Courses.')
                                        ->default(true)
                                        ->columnSpanFull(),
                                    TextInput::make('home_courses_eyebrow')->label('Eyebrow label')->maxLength(80),
                                    TextInput::make('home_courses_title')->label('Heading')->maxLength(255)->columnSpanFull(),
                                    Textarea::make('home_courses_subtitle')->label('Subtitle')->rows(2)->columnSpanFull(),
                                ])
                                ->columns(2),
                            Section::make('Bottom call to action')
                                ->schema([
                                    TextInput::make('home_cta_title')->label('Heading')->maxLength(255)->columnSpanFull(),
                                    Textarea::make('home_cta_subtitle')->label('Subtitle')->rows(2)->columnSpanFull(),
                                ]),
                            Section::make('Hero highlights')
                                ->schema([
                                    Repeater::make('hero_stats')
                                        ->label('Hero stat blocks')
                                        ->schema([
                                            TextInput::make('title')->required()->maxLength(40),
                                            TextInput::make('subtitle')->required()->maxLength(80),
                                        ])
                                        ->columns(2)
                                        ->defaultItems(3)
                                        ->minItems(1)
                                        ->maxItems(4)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Tab::make('Gallery')
                        ->icon(Heroicon::OutlinedRectangleGroup)
                        ->schema([
                            Repeater::make('gallery_items')
                                ->label('Gallery Images')
                                ->schema([
                                    Hidden::make('id'),
                                    FileUpload::make('image_path')
                                        ->label('Image')
                                        ->required()
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
                                        ->directory('site/gallery')
                                        ->visibility('public')
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                        ->helperText('Max 350 KB. Removing the image deletes it from the server on save.')
                                        ->columnSpanFull(),
                                    TextInput::make('caption')->required()->maxLength(120),
                                    TextInput::make('alt')->label('Alt Text')->required()->maxLength(255),
                                    Select::make('span_class')
                                        ->label('Layout Size')
                                        ->options([
                                            '' => 'Standard',
                                            'sm:col-span-2 sm:min-h-[280px] lg:col-span-2 lg:row-span-2' => 'Large (featured)',
                                            'sm:col-span-2 sm:min-h-[240px] lg:col-span-2' => 'Wide',
                                        ])
                                        ->native(false),
                                ])
                                ->columns(2)
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['caption'] ?? 'New image')
                                ->columnSpanFull(),
                        ]),
                    Tab::make('Social')
                        ->icon(Heroicon::OutlinedShare)
                        ->schema([
                            TextInput::make('social_facebook')->label('Facebook URL')->url(),
                            TextInput::make('social_instagram')->label('Instagram URL')->url(),
                            TextInput::make('social_youtube')->label('YouTube URL')->url(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    protected function imageUpload(string $name, string $label, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
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
            ->directory($directory)
            ->visibility('public')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->helperText('Max 350 KB. Replace or clear to upload a new image — old file is removed on save.');
    }

    public function save(SiteContentService $siteContent): void
    {
        $data = $this->form->getState();
        $siteContent->save($data);

        Notification::make()
            ->title('Website content saved')
            ->body('Changes are live on the public site.')
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
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save Website Content')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }
}
