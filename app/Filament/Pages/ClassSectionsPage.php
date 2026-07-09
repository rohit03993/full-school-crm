<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Filament\Resources\Batches\BatchResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Services\ClassSectionListService;
use App\Services\CourseLifecycleService;
use App\Support\ClassSectionLabel;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use App\Support\InstituteTerminology;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use UnitEnum;

class ClassSectionsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?string $slug = 'classes-sections';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::classes();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::classes();
    }

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

    public function deleteProgramme(int $courseId, CourseLifecycleService $lifecycle): void
    {
        abort_unless(static::canAccess(), 403);

        $course = Course::query()->findOrFail($courseId);

        try {
            $lifecycle->deleteProgrammeWithAllSections($course);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not delete class')
                ->body(collect($exception->errors())->flatten()->first() ?? 'This class cannot be deleted.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Class deleted')
            ->body('All sections were removed and the class no longer appears on the website.')
            ->success()
            ->send();

        $this->resetPage();
        $this->refreshStats();
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
                    'courseSubjectsUrl' => fn (int $id): string => CourseResource::getUrl('edit', ['record' => $id]).'?panel=subjects',
                    'displayLabel' => fn ($batch): string => ClassSectionLabel::forBatch($batch, includeSession: false),
                ]),
        ]);
    }
}
