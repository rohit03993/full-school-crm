<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\BatchStaffAssignmentService;
use App\Services\StudentConductParentNoticeService;
use App\Services\StudentConductService;
use App\Support\ConductTypeOptions;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use UnitEnum;

class StudentReportsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $slug = 'student-reports';

    protected static ?int $navigationSort = 34;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public string $search = '';

    public string $fineFilter = '';

    public string $typeFilter = '';

    public string $reportMode = 'one';

    public string $bulkBatchId = '';

    public string $singleStudentId = '';

    /** @var list<int|string> */
    public array $selectedStudentIds = [];

    /** @var list<int|string> */
    public array $noticeIds = [];

    public string $bulkRecordedOn = '';

    public string $bulkTypeKey = '';

    public string $bulkFineChoice = 'without_fine';

    public string $bulkAmount = '';

    public string $bulkNote = '';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::studentReports();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::studentReports();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('students.reports');
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('students.reports');
    }

    public static function canAccess(): bool
    {
        return app(StudentConductService::class)->canRecord(Auth::user());
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $viewer = Auth::user();
        $total = app(StudentConductService::class)->stats($viewer instanceof User ? $viewer : null)['total'];

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public function mount(): void
    {
        $this->bulkRecordedOn = now()->toDateString();
        $options = app(BatchStaffAssignmentService::class)->activeBatchOptionsFor(Auth::user());

        if (count($options) === 1) {
            $this->bulkBatchId = (string) array_key_first($options);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFineFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function setReportMode(string $mode): void
    {
        if (! in_array($mode, ['one', 'many', 'saved'], true) || $mode === $this->reportMode) {
            return;
        }

        $this->reportMode = $mode;
        $this->selectedStudentIds = [];
        $this->singleStudentId = '';
        $this->noticeIds = [];
        $this->resetReportDraft();

        if ($mode === 'saved') {
            $this->resetPage();
        }
    }

    public function updatedBulkBatchId(): void
    {
        $this->selectedStudentIds = [];
        $this->singleStudentId = '';
    }

    public function saveSingleReport(): void
    {
        $this->storeReports([(int) $this->singleStudentId]);
    }

    public function toggleSelectAllStudents(): void
    {
        $ids = $this->currentClassStudentIds();
        $selected = array_map(strval(...), $this->selectedStudentIds);
        $allSelected = $ids !== [] && array_diff($ids, $selected) === [];
        $this->selectedStudentIds = $allSelected ? [] : $ids;
    }

    public function sendBulkReport(): void
    {
        $this->storeReports($this->selectedStudentIds);
    }

    /**
     * @param  list<int|string>  $studentIds
     */
    protected function storeReports(array $studentIds): void
    {
        $staff = Auth::user();

        if (! $staff instanceof User) {
            return;
        }

        try {
            $records = app(StudentConductService::class)->storeMany($staff, $studentIds, [
                'recorded_on' => $this->bulkRecordedOn,
                'type_keys' => $this->bulkTypeKey !== '' ? [$this->bulkTypeKey] : [],
                'fine_choice' => $this->bulkFineChoice,
                'amount' => $this->bulkAmount,
                'note' => $this->bulkNote,
            ]);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?: 'Could not save the reports.')
                ->danger()
                ->send();

            return;
        }

        $count = $records->count();

        $this->selectedStudentIds = [];
        $this->singleStudentId = '';
        $this->resetReportDraft();
        $this->resetPage();

        Notification::make()
            ->title($count === 1 ? 'Report saved for 1 student' : 'Reports saved for '.$count.' students')
            ->body('The parent message waits until Super Admin or Academic coordinator approves it.')
            ->success()
            ->send();
    }

    public function approveSelectedNotices(): void
    {
        $this->decideSelectedNotices(true);
    }

    public function declineSelectedNotices(): void
    {
        $this->decideSelectedNotices(false);
    }

    protected function decideSelectedNotices(bool $approve): void
    {
        $staff = Auth::user();

        if (! $staff instanceof User) {
            return;
        }

        $result = $approve
            ? app(StudentConductParentNoticeService::class)->approve($this->noticeIds, $staff)
            : app(StudentConductParentNoticeService::class)->decline($this->noticeIds, $staff);

        $this->noticeIds = [];
        $this->resetPage();

        $done = $approve ? ($result['sent'] ?? 0) > 0 : ($result['declined'] ?? 0) > 0;

        Notification::make()
            ->title($result['note'])
            ->{$done ? 'success' : 'warning'}()
            ->send();
    }

    protected function resetReportDraft(): void
    {
        $this->bulkTypeKey = '';
        $this->bulkFineChoice = 'without_fine';
        $this->bulkAmount = '';
        $this->bulkNote = '';
        $this->bulkRecordedOn = now()->toDateString();
    }

    public function content(Schema $schema): Schema
    {
        $service = app(StudentConductService::class);
        $viewer = Auth::user();
        $viewer = $viewer instanceof User ? $viewer : null;

        return $schema->components([
            View::make('filament.pages.partials.student-reports')
                ->viewData(fn (): array => [
                    'reportMode' => $this->reportMode,
                    'reports' => $this->reportMode === 'saved'
                        ? $service->paginate(
                            $this->search,
                            $this->fineFilter,
                            $this->typeFilter,
                            CrmPagination::PER_PAGE,
                            $viewer,
                        )
                        : null,
                    'stats' => $this->reportMode === 'saved' ? $service->stats($viewer) : null,
                    'typeOptions' => ConductTypeOptions::formOptions(),
                    'classOptions' => app(BatchStaffAssignmentService::class)->activeBatchOptionsFor($viewer),
                    'bulkStudents' => $viewer && $this->reportMode !== 'saved'
                        ? $service->studentsInClass($viewer, (int) $this->bulkBatchId)
                        : collect(),
                    'bulkBatchId' => $this->bulkBatchId,
                    'singleStudentId' => $this->singleStudentId,
                    'selectedStudentIds' => $this->selectedStudentIds,
                    'bulkFineChoice' => $this->bulkFineChoice,
                    'allStudentsSelected' => $this->reportMode === 'many' && $this->allStudentsSelected(),
                    'canApproveParentNotice' => $viewer && $service->canApproveParentNotice($viewer),
                    'noticeIds' => $this->noticeIds,
                ]),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function currentClassStudentIds(): array
    {
        $viewer = Auth::user();

        if (! $viewer instanceof User) {
            return [];
        }

        return app(StudentConductService::class)
            ->studentsInClass($viewer, (int) $this->bulkBatchId)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    protected function allStudentsSelected(): bool
    {
        $ids = $this->currentClassStudentIds();
        $selected = array_map(strval(...), $this->selectedStudentIds);

        return $ids !== [] && array_diff($ids, $selected) === [];
    }
}
