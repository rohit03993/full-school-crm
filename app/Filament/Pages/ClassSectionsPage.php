<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Filament\Resources\Batches\BatchResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\AcademicSession;
use App\Services\ClassSectionListService;
use App\Support\ClassSectionLabel;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use App\Support\InstituteTerminology;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class ClassSectionsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Classes & sections';

    protected static ?string $title = 'Classes & sections';

    protected static ?string $slug = 'classes-sections';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public string $search = '';

    public ?int $sessionFilter = null;

    public int $perPage = CrmPagination::PER_PAGE;

    /**
     * @var array{sections: int, programmes: int, students: int}
     */
    public array $stats = [
        'sections' => 0,
        'programmes' => 0,
        'students' => 0,
    ];

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::AcademicsManage);
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('class_sections.list');
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('class_sections.list');
    }

    public function mount(): void
    {
        $this->sessionFilter = AcademicSession::current()?->id;
        $this->refreshStats();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedSessionFilter(mixed $value): void
    {
        $this->sessionFilter = filled($value) ? (int) $value : null;
        $this->resetPage();
        $this->refreshStats();
    }

    protected function refreshStats(): void
    {
        $this->stats = app(ClassSectionListService::class)->stats($this->sessionFilter);
    }

    public function content(Schema $schema): Schema
    {
        $service = app(ClassSectionListService::class);

        return $schema->components([
            View::make('filament.pages.partials.class-sections-list')
                ->viewData(fn (): array => [
                    'sections' => $service->paginate(
                        $this->sessionFilter,
                        $this->search,
                        page: $this->getPage(),
                    ),
                    'stats' => $this->stats,
                    'search' => $this->search,
                    'sessionFilter' => $this->sessionFilter,
                    'sessionOptions' => AcademicSession::query()
                        ->where('is_active', true)
                        ->orderByDesc('starts_on')
                        ->pluck('name', 'id')
                        ->all(),
                    'courseLabel' => InstituteTerminology::label('course'),
                    'batchLabel' => InstituteTerminology::label('batch'),
                    'addUrl' => AddClassSectionPage::getUrl(),
                    'batchEditUrl' => fn (int $id): string => BatchResource::getUrl('edit', ['record' => $id]),
                    'courseEditUrl' => fn (int $id): string => CourseResource::getUrl('edit', ['record' => $id]),
                    'displayLabel' => fn ($batch): string => ClassSectionLabel::forBatch($batch, includeSession: false),
                ]),
        ]);
    }
}
