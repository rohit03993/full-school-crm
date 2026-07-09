<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Filament\Pages\ConsolidatedReportCardsPage;
use App\Filament\Pages\ExamWindowsPage;
use App\Filament\Pages\TestMarksReviewPage;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Filament\Resources\ActivityTypes\ActivityTypeResource;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Services\ResultDeclarationService;
use App\Support\CrmMenuLabels;
use App\Support\ExamTestGroupMatrix;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema as DbSchema;

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

    public function getBreadcrumb(): ?string
    {
        return null;
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->activitySchemaReady()) {
            return $schema->components([
                View::make('filament.resources.activity-sessions.schema-repair-notice'),
            ]);
        }

        $matrix = ExamTestGroupMatrix::build($this->batchFilter, $this->activityTypeFilter);

        return $schema->components([
            View::make('filament.resources.activity-sessions.exam-test-groups-list')
                ->viewData(fn (): array => [
                    'matrix' => $matrix,
                    'batchOptions' => Batch::query()->orderBy('name')->pluck('name', 'id')->all(),
                    'activityTypeOptions' => ActivityType::scoringOptions(),
                    'importMarksUrl' => BulkActivityMarksImportPage::getUrl(),
                    'reviewPageBaseUrl' => TestMarksReviewPage::getUrl(),
                    'declarationStatuses' => collect($matrix['rows'] ?? [])
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

        if (BulkActivityMarksImportPage::canAccess()) {
            $actions[] = Action::make('uploadMarks')
                ->label(CrmMenuLabels::uploadMarksExcel())
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('primary')
                ->url(BulkActivityMarksImportPage::getUrl());
        }

        if (ExamWindowsPage::canAccess()) {
            $actions[] = Action::make('examWindows')
                ->label(CrmMenuLabels::createExam())
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('success')
                ->url(ExamWindowsPage::getUrl())
                ->tooltip('Create exams from programme subjects — teacher entry, approval, then publish.');
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

        if (ActivitySessionResource::canCreate()) {
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
