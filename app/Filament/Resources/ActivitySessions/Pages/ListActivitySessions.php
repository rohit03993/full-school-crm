<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Enums\RoleName;
use App\Filament\Pages\CreateExamWindowPage;
use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Filament\Pages\ConsolidatedReportCardsPage;
use App\Filament\Pages\ExamWindowsPage;
use App\Filament\Pages\TestMarksReviewPage;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Filament\Resources\ActivityTypes\ActivityTypeResource;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Services\ExamTestGroupService;
use App\Services\ExamWindowService;
use App\Services\ResultDeclarationService;
use App\Support\CrmMenuLabels;
use App\Support\CrmPagination;
use App\Support\ExamMarksPath;
use App\Support\ExamTestGroupMatrix;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Validation\ValidationException;

class ListActivitySessions extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = ActivitySessionResource::class;

    protected static ?string $title = null;

    protected static function crmHintKey(): ?string
    {
        return 'activity.sessions.list';
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::examResults();
    }

    public ?int $batchFilter = null;

    public ?int $activityTypeFilter = null;

    public int $examPage = 1;

    public ?string $renameGroupKey = null;

    public string $renameExamName = '';

    public function updatedBatchFilter(): void
    {
        $this->examPage = 1;
    }

    public function updatedActivityTypeFilter(): void
    {
        $this->examPage = 1;
    }

    public function gotoExamPage(int $page): void
    {
        $this->examPage = max(1, $page);
    }

    public function deleteExam(string $groupKey): void
    {
        $user = Auth::user();
        $service = app(ExamTestGroupService::class);

        if (! $user || ! $service->userCanManage($user)) {
            Notification::make()
                ->title('Not allowed')
                ->body('You do not have permission to delete exams.')
                ->warning()
                ->send();

            return;
        }

        try {
            $service->deleteGroup($user, $groupKey);

            Notification::make()
                ->title('Exam deleted')
                ->body('This exam and its marks were removed. Other exams were not changed.')
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Exam not deleted')
                ->body(collect($exception->errors())->flatten()->first() ?: 'This exam cannot be deleted.')
                ->danger()
                ->send();
        }
    }

    public function startRename(string $groupKey, string $currentName): void
    {
        if (! Auth::user() || ! app(ExamTestGroupService::class)->userCanManage(Auth::user())) {
            Notification::make()->title('Not allowed')->warning()->send();

            return;
        }

        $this->renameGroupKey = $groupKey;
        $this->renameExamName = $currentName;
    }

    public function cancelRename(): void
    {
        $this->renameGroupKey = null;
        $this->renameExamName = '';
    }

    public function saveRename(): void
    {
        $user = Auth::user();
        $service = app(ExamTestGroupService::class);

        if (! $user || ! $service->userCanManage($user) || blank($this->renameGroupKey)) {
            Notification::make()->title('Not allowed')->warning()->send();

            return;
        }

        try {
            $service->renameGroup($user, (string) $this->renameGroupKey, $this->renameExamName);

            Notification::make()
                ->title('Exam renamed')
                ->body('Marks were not changed. Only the name staff see was updated.')
                ->success()
                ->send();

            $this->cancelRename();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Name not saved')
                ->body(collect($exception->errors())->flatten()->first() ?: 'Could not rename this exam.')
                ->danger()
                ->send();
        }
    }

    public function getBreadcrumb(): ?string
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->activitySchemaReady()) {
            return $schema->components([
                View::make('filament.resources.activity-sessions.schema-repair-notice'),
            ]);
        }

        $subjectScope = app(ExamWindowService::class)->assignedSubjectScope(Auth::user());
        $matrix = ExamTestGroupMatrix::build($this->batchFilter, $this->activityTypeFilter, $subjectScope);
        $allRows = $matrix['rows'] ?? [];
        $perPage = CrmPagination::PER_PAGE;
        $lastPage = max(1, (int) ceil(count($allRows) / $perPage));

        if ($this->examPage > $lastPage) {
            $this->examPage = $lastPage;
        }

        $exams = new LengthAwarePaginator(
            array_slice($allRows, ($this->examPage - 1) * $perPage, $perPage),
            count($allRows),
            $perPage,
            $this->examPage,
            ['path' => request()->url(), 'pageName' => 'examPage'],
        );

        $pageRows = $exams->items();
        $groupKeys = collect($pageRows)
            ->pluck('group_key')
            ->filter()
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();

        $deleteEligibility = app(ExamTestGroupService::class)->deleteEligibility($groupKeys);
        $canDeleteExams = Auth::user()
            ? app(ExamTestGroupService::class)->userCanManage(Auth::user())
            : false;
        $createTeacherExamUrl = CreateExamWindowPage::canAccess() ? CreateExamWindowPage::getUrl() : null;
        $uploadExcelUrl = BulkActivityMarksImportPage::canAccess() ? BulkActivityMarksImportPage::getUrl() : null;
        $teacherExamsListUrl = CreateExamWindowPage::canAccess() ? ExamWindowsPage::getUrl() : null;

        return $schema->components([
            View::make('filament.resources.activity-sessions.exam-test-groups-list')
                ->viewData(fn (): array => [
                    'matrix' => $matrix,
                    'exams' => $exams,
                    'canDeleteExams' => $canDeleteExams,
                    'canRenameExams' => $canDeleteExams,
                    'renameGroupKey' => $this->renameGroupKey,
                    'renameExamName' => $this->renameExamName,
                    'deleteEligibility' => $deleteEligibility,
                    'batchOptions' => Batch::query()
                        ->when(
                            $subjectScope !== null,
                            fn ($query) => $query->whereIn('id', $subjectScope['batch_ids'] === [] ? [-1] : $subjectScope['batch_ids']),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all(),
                    'activityTypeOptions' => ActivityType::scoringOptions(),
                    'importMarksUrl' => BulkActivityMarksImportPage::getUrl(),
                    'reviewPageBaseUrl' => TestMarksReviewPage::getUrl(),
                    'createTeacherExamUrl' => $createTeacherExamUrl,
                    'uploadExcelUrl' => $uploadExcelUrl,
                    'teacherExamsListUrl' => $teacherExamsListUrl,
                    'entryMeta' => ExamMarksPath::forGroupKeys($groupKeys),
                    'declarationStatuses' => collect($pageRows)
                        ->mapWithKeys(fn (array $row): array => [
                            (string) ($row['group_key'] ?? '') => ResultDeclarationService::statusMetaForGroupKey((string) ($row['group_key'] ?? '')),
                        ])
                        ->all(),
                ]),
        ]);
    }

    protected function activitySchemaReady(): bool
    {
        return DbSchema::hasTable('activity_types')
            && DbSchema::hasTable('activity_sessions');
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (CreateExamWindowPage::canAccess()) {
            $actions[] = Action::make('teachersEnterMarks')
                ->label(CrmMenuLabels::createExam())
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('success')
                ->url(CreateExamWindowPage::getUrl())
                ->tooltip('Create the exam for a class. Teachers type marks per subject.');
        }

        if (BulkActivityMarksImportPage::canAccess()) {
            $actions[] = Action::make('uploadMarks')
                ->label(CrmMenuLabels::uploadMarksExcel())
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('primary')
                ->url(BulkActivityMarksImportPage::getUrl())
                ->tooltip('Name the exam and upload a spreadsheet of marks.');
        }

        if (CreateExamWindowPage::canAccess()) {
            $actions[] = Action::make('teacherExamsInProgress')
                ->label(CrmMenuLabels::teacherExamsInProgress())
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(ExamWindowsPage::getUrl())
                ->tooltip('Draft, teacher-entry, and waiting-approval exams.');
        }

        if (ConsolidatedReportCardsPage::canAccess()) {
            $actions[] = Action::make('consolidatedReportCards')
                ->label('Consolidated report cards')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->url(ConsolidatedReportCardsPage::getUrl())
                ->tooltip('Combine Term 1 + Term 2 (or more) into one PDF per student.');
        }

        if ($this->activitySchemaReady() && ActivityType::scoringTypes()->isEmpty()) {
            if (ActivityTypeResource::canAccess()) {
                $actions[] = Action::make('setupActivityTypes')
                    ->label('Set up exam type')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('warning')
                    ->url(ActivityTypeResource::getUrl('index'));
            }
        }

        if (ActivitySessionResource::canCreate() && Auth::user()?->hasRole(RoleName::SuperAdmin->value)) {
            $actions[] = Action::make('scheduleSingleSubject')
                ->label('One subject (manual)')
                ->icon(Heroicon::OutlinedPlus)
                ->color('gray')
                ->url(CreateActivitySession::getUrl())
                ->tooltip('Rare: schedule a single subject and type marks student by student.');
        }

        return $actions;
    }
}
