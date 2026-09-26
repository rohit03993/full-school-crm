<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\ExamWindowStatus;
use App\Enums\LicenseFeature;
use App\Models\ExamWindow;
use App\Services\ExamWindowService;
use App\Support\ClassSectionLabel;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use App\Support\FeatureGate;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class ExamWindowsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?string $slug = 'exam-windows';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 25;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::teacherExamsInProgress();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::teacherExamsInProgress();
    }

    public string $search = '';

    public ?string $statusFilter = null;

    public int $perPage = CrmPagination::PER_PAGE;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Marks)) {
            return false;
        }

        return CrmAccess::canAny(
            Auth::user(),
            CrmPermission::AcademicsManage,
            CrmPermission::MarksImport,
        );
    }

    public static function getNavigationTooltip(): ?string
    {
        return 'Create exams from the selected section subjects — teachers enter marks, class lead submits, admin approves.';
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('exam_windows.list');
    }

    protected function getHeaderActions(): array
    {
        if (! CreateExamWindowPage::canAccess()) {
            return [];
        }

        return [
            Action::make('create')
                ->label(CrmMenuLabels::createExam())
                ->icon(Heroicon::OutlinedPlus)
                ->url(CreateExamWindowPage::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $query = ExamWindow::query()
            ->with(['batch.course', 'batch.academicSession', 'activityType', 'subjects'])
            ->orderByDesc('session_date')
            ->orderByDesc('id');

        if (filled($this->search)) {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('test_name', 'like', $term)
                    ->orWhereHas('batch', fn ($batch) => $batch
                        ->where('name', 'like', $term)
                        ->orWhere('section', 'like', $term))
                    ->orWhereHas('batch.course', fn ($course) => $course->where('name', 'like', $term));
            });
        }

        if (filled($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        $subjectScope = app(ExamWindowService::class)->assignedSubjectScope(Auth::user());

        if ($subjectScope !== null) {
            $batchIds = $subjectScope['batch_ids'] === [] ? [-1] : $subjectScope['batch_ids'];
            $subjectIds = $subjectScope['subject_ids'] === [] ? [-1] : $subjectScope['subject_ids'];
            $query->whereIn('batch_id', $batchIds)
                ->whereHas('subjects', fn ($subjects) => $subjects->whereIn('course_subject_id', $subjectIds));
        }

        $windows = $query->paginate($this->perPage);

        return $schema->components([
            View::make('filament.pages.partials.exam-windows-list')
                ->viewData(fn (): array => [
                    'windows' => $windows,
                    'statusOptions' => collect(ExamWindowStatus::cases())
                        ->mapWithKeys(fn (ExamWindowStatus $status): array => [$status->value => $status->label()])
                        ->all(),
                    'createUrl' => CreateExamWindowPage::getUrl(),
                    'detailUrl' => fn (int $id): string => ExamWindowPage::getUrl(['window' => $id]),
                    'displayBatch' => fn (ExamWindow $window): string => $window->batch
                        ? ClassSectionLabel::forBatch($window->batch, includeSession: false, includeShift: false)
                        : '—',
                ]),
        ]);
    }
}
