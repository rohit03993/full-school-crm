<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Filament\Pages\ClassSectionsPage;
use App\Filament\Pages\ExamWindowsPage;
use App\Filament\Resources\AcademicSessions\AcademicSessionResource;
use App\Models\AcademicSession;
use App\Support\CrmNavigation;
use App\Support\InstituteOnboarding;
use App\Support\InstituteSettings;
use App\Support\InstituteTerminology;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class InstituteSetup extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Institute Setup';

    protected static ?string $title = 'Institute Setup';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('setup.institute');
    }

    public function getSubheading(): ?string
    {
        return 'Quick links to configure programmes, terminology, website content, and academic structure.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.setup-checklist')
                ->viewData(fn (): array => [
                    'onboardingComplete' => InstituteOnboarding::isComplete(),
                ]),
            View::make('filament.pages.partials.institute-setup-snapshot')
                ->viewData(fn (): array => [
                    'snapshot' => $this->instituteSnapshot(),
                ]),
            View::make('filament.pages.partials.institute-setup-links')
                ->viewData(fn (): array => [
                    'links' => $this->setupLinks(),
                ]),
        ]);
    }

    /**
     * @return array<int, array{label: string, description: string, url: string, icon: string}>
     */
    public function setupLinks(): array
    {
        $courseLabel = InstituteTerminology::label('course');

        return [
            [
                'label' => 'Setup Guide',
                'description' => 'Step-by-step install and customize instructions.',
                'url' => SetupGuide::getUrl(),
                'icon' => 'heroicon-o-book-open',
            ],
            [
                'label' => 'Academic Sessions',
                'description' => 'Academic years for your batches (e.g. 2025–26).',
                'url' => AcademicSessionResource::getUrl(),
                'icon' => 'heroicon-o-calendar-days',
            ],
            [
                'label' => 'Exam windows',
                'description' => 'Create unit tests and term exams from programme subjects.',
                'url' => ExamWindowsPage::getUrl(),
                'icon' => 'heroicon-o-clipboard-document-check',
            ],
            [
                'label' => 'Classes & sections',
                'description' => "Manage {$courseLabel}s, sections, fees, subjects, and teachers.",
                'url' => ClassSectionsPage::getUrl(),
                'icon' => 'heroicon-o-academic-cap',
            ],
            [
                'label' => 'Terminology',
                'description' => 'Rename Course, Batch, Roll number, and other labels for your institute.',
                'url' => ManageTerminology::getUrl(),
                'icon' => 'heroicon-o-language',
            ],
            [
                'label' => 'Custom Fields',
                'description' => 'Add extra fields on student profiles (blood group, transport route, etc.).',
                'url' => ManageCustomFields::getUrl(),
                'icon' => 'heroicon-o-adjustments-horizontal',
            ],
            [
                'label' => 'Biometric Attendance',
                'description' => 'EasyTimePro punch_logs setup, roll mapping, and processor checklist.',
                'url' => ManageAttendanceBiometricPage::getUrl(),
                'icon' => 'heroicon-o-finger-print',
            ],
            [
                'label' => 'Fees dashboard',
                'description' => 'Collections, defaulters, and overdue installments.',
                'url' => FeesDashboardPage::getUrl(),
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Accounting ledger',
                'description' => 'Double-entry journal from fee receipts and late-fee accruals.',
                'url' => AccountingLedgerPage::getUrl(),
                'icon' => 'heroicon-o-calculator',
            ],
            [
                'label' => 'WhatsApp — Connection & Setup',
                'description' => 'This institute\'s Meta credentials, webhook, sync templates, enable routing.',
                'url' => ManageMetaWhatsAppSettings::getUrl(),
                'icon' => 'heroicon-o-device-phone-mobile',
            ],
            [
                'label' => 'WhatsApp — Automations',
                'description' => 'Map punch IN/OUT, post-call, fee reminders, and campaign batch settings to approved templates.',
                'url' => ManageWhatsAppSettings::getUrl(),
                'icon' => 'heroicon-o-cog-8-tooth',
            ],
            [
                'label' => 'Institute Settings',
                'description' => 'Receipt logo, PDF header/footer for receipts, ID cards, and reports.',
                'url' => ManageInstituteSettings::getUrl(),
                'icon' => 'heroicon-o-building-office-2',
            ],
            [
                'label' => 'Website Content',
                'description' => 'Public site branding, hero text, contact details, gallery, and homepage sections.',
                'url' => ManageSiteContent::getUrl(),
                'icon' => 'heroicon-o-globe-alt',
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function instituteSnapshot(): array
    {
        $session = AcademicSession::current();

        return [
            'name' => InstituteSettings::brandName(),
            'prefix' => InstituteSettings::numberPrefix(),
            'session' => $session?->name,
        ];
    }
}
