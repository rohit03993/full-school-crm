<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Filament\Pages\SessionAttendancePage;
use App\Filament\Pages\TestMarksReviewPage;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Filament\Resources\ActivityTypes\ActivityTypeResource;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Services\ResultDeclarationService;
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

    protected static ?string $title = 'Tests & Exams';

    protected static function crmHintKey(): ?string
    {
        return 'activity.sessions.list';
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
                    'activityTypeOptions' => ActivityType::query()->enabled()->ordered()->pluck('name', 'id')->all(),
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
                ->label('Upload marks (Excel)')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('primary')
                ->url(BulkActivityMarksImportPage::getUrl());
        }

        if ($this->activitySchemaReady() && ! ActivityType::query()->enabled()->exists()) {
            if (ActivityTypeResource::canAccess()) {
                $actions[] = Action::make('setupActivityTypes')
                    ->label('Set up exam types first')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('warning')
                    ->url(ActivityTypeResource::getUrl('index'));
            }
        }

        if (SessionAttendancePage::canAccess()) {
            $actions[] = Action::make('sessionAttendance')
                ->label('Workshop / event attendance')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info')
                ->url(SessionAttendancePage::getUrl());
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
