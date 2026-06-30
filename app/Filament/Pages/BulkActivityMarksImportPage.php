<?php

namespace App\Filament\Pages;

use App\Enums\BatchStatus;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Exports\ActivityMarksImportTemplateExport;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\AcademicSession;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityMarksBulkImportService;
use App\Services\ActivityMarksImportColumnMapper;
use App\Services\ActivityMarksWhatsAppService;
use App\Services\StudentImportFileReader;
use App\Support\CrmNavigation;
use App\Support\EduExamLabels;
use App\Support\ExamSubjectCatalog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

class BulkActivityMarksImportPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Import Marks';

    protected static ?string $title = 'Upload marks';

    protected static ?int $navigationSort = 55;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public function getSubheading(): ?string
    {
        return 'Name the test, upload Excel — roll numbers and all subject columns in one file.';
    }

    public int $step = 1;

    public ?int $academicSessionId = null;

    public ?int $activityTypeId = null;

    public string $testName = '';

    public ?string $sessionDate = null;

    public float $defaultMaxMarks = 100;

    public bool $limitToBatch = false;

    public ?int $batchId = null;

    public ?TemporaryUploadedFile $uploadFile = null;

    public string $storedFilePath = '';

    public string $originalFilename = '';

    /**
     * @var list<string|null>
     */
    public array $fileHeaders = [];

    /**
     * @var list<list<string|null>>
     */
    public array $fileRows = [];

    /**
     * @var array{roll_column: int|null, subject_columns: list<int>}
     */
    public array $columnMapping = [
        'roll_column' => null,
        'subject_columns' => [],
    ];

    /**
     * @var array<int, float|int|string>
     */
    public array $subjectMaxMarks = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $previewPayload = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $importResult = null;

    public ?int $whatsappTemplateId = null;

    public ?string $importError = null;

    public function mount(): void
    {
        $this->academicSessionId = AcademicSession::current()?->id;
        $this->sessionDate = request()->query('date', now()->toDateString());

        if (filled(request()->query('test_name'))) {
            $this->testName = (string) request()->query('test_name');
        }

        $activityTypeId = request()->integer('activity_type_id');

        if ($activityTypeId > 0) {
            $this->activityTypeId = $activityTypeId;
        }

        $batchId = request()->integer('batch_id');

        if ($batchId > 0) {
            $this->batchId = $batchId;
            $this->limitToBatch = true;
        }
    }

    /**
     * @param  array<string, scalar|null>  $params
     */
    public static function urlForTest(
        string $testName,
        ?int $activityTypeId = null,
        ?int $batchId = null,
        ?string $sessionDate = null,
    ): string {
        $params = array_filter([
            'test_name' => $testName,
            'activity_type_id' => $activityTypeId,
            'batch_id' => $batchId,
            'date' => $sessionDate,
        ], fn (mixed $value): bool => filled($value));

        $base = static::getUrl();

        return $params === [] ? $base : $base.'?'.http_build_query($params);
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Marks)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::MarksImport);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToTests')
                ->label('Back to '.EduExamLabels::tests())
                ->color('gray')
                ->url(ActivitySessionResource::getUrl('index')),
        ];
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new ActivityMarksImportTemplateExport,
            'marks-import-template.xlsx',
        );
    }

    public function parseFileAndContinue(
        StudentImportFileReader $reader,
        ActivityMarksImportColumnMapper $mapper,
    ): void {
        $rules = [
            'activityTypeId' => 'required|exists:activity_types,id',
            'testName' => 'required|string|max:255',
            'sessionDate' => 'required|date',
            'defaultMaxMarks' => 'required|numeric|min:1|max:9999',
            'uploadFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ];

        if ($this->limitToBatch) {
            $rules['batchId'] = 'required|exists:batches,id';
        }

        $this->validate($rules);

        $activityType = ActivityType::query()->findOrFail($this->activityTypeId);

        if (! $activityType->supportsScoring()) {
            Notification::make()
                ->title('Exam type cannot record marks')
                ->body('Choose a type with marks enabled, or create one with “Records marks & scores”.')
                ->danger()
                ->send();

            return;
        }

        if ($this->storedFilePath) {
            $reader->deleteStoredFile($this->storedFilePath);
        }

        $parsed = $reader->storeAndParse($this->uploadFile, detectMarksHeaderRow: true);

        $this->storedFilePath = $parsed['path'];
        $this->originalFilename = $this->uploadFile->getClientOriginalName();
        $this->fileHeaders = $parsed['headers'];
        $this->fileRows = $parsed['rows'];
        $this->columnMapping = $mapper->guess($this->fileHeaders);
        $this->syncSubjectMaxMarksFromMapping();
        $this->previewPayload = null;
        $this->importResult = null;
        $this->importError = null;
        $this->uploadFile = null;
        $this->step = 2;
    }

    public function buildPreview(ActivityMarksBulkImportService $importService): void
    {
        $missing = app(ActivityMarksImportColumnMapper::class)->missingRequiredFields($this->columnMapping);

        if ($missing !== []) {
            Notification::make()
                ->title('Column mapping incomplete')
                ->body('Map: '.implode(', ', $missing))
                ->danger()
                ->send();

            return;
        }

        $this->previewPayload = $importService->buildPreview(
            $this->fileHeaders,
            $this->fileRows,
            $this->columnMapping,
            $this->academicSessionId,
            $this->limitToBatch ? $this->batchId : null,
            $this->defaultMaxMarks,
            $this->subjectMaxMarks,
        );

        $this->step = 3;
    }

    public function runImport(ActivityMarksBulkImportService $importService): void
    {
        if (! is_array($this->previewPayload)) {
            Notification::make()
                ->title('Preview missing')
                ->body('Go back and preview the file again before importing.')
                ->warning()
                ->send();

            return;
        }

        if (($this->previewPayload['ready_count'] ?? 0) === 0) {
            Notification::make()
                ->title('Nothing to import')
                ->body('Fix validation errors in the preview first.')
                ->warning()
                ->send();

            return;
        }

        try {
            $activityType = ActivityType::query()->findOrFail($this->activityTypeId);

            $this->importResult = $importService->import(
                Auth::user(),
                $activityType,
                $this->testName,
                (string) $this->sessionDate,
                $this->defaultMaxMarks,
                $this->previewPayload['rows'],
                $this->previewPayload['subject_max_marks'] ?? [],
            );

            $this->step = 4;
            $this->previewPayload = null;
            $this->fileRows = [];
            $this->fileHeaders = [];

            Notification::make()
                ->title('Marks imported')
                ->body(($this->importResult['marks_saved'] ?? 0).' mark(s) saved for '.($this->importResult['students'] ?? 0).' student(s).')
                ->success()
                ->duration(10000)
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            $this->importError = config('app.debug')
                ? $exception->getMessage()
                : 'Import failed. Check the file and try again.';

            Notification::make()
                ->title('Import failed')
                ->body($this->importError)
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function queueWhatsAppCampaign(ActivityMarksWhatsAppService $marksWhatsApp): void
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            Notification::make()
                ->title('WhatsApp module is not enabled')
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'whatsappTemplateId' => 'required|exists:whatsapp_templates,id',
        ]);

        if (! is_array($this->importResult) || blank($this->importResult['test_key'] ?? null)) {
            Notification::make()
                ->title('Import result missing')
                ->body('Import marks first, then send WhatsApp messages.')
                ->warning()
                ->send();

            return;
        }

        try {
            $campaign = $marksWhatsApp->queueMarksCampaign(
                Auth::user(),
                (int) $this->whatsappTemplateId,
                (string) $this->importResult['test_key'],
                (string) $this->importResult['test_name'],
                (string) $this->importResult['session_date'],
            );

            Notification::make()
                ->title('WhatsApp campaign queued')
                ->body($campaign->total_recipients.' parent/student message(s) queued.')
                ->success()
                ->duration(10000)
                ->send();

            $this->redirect(WhatsAppCampaignResource::getUrl('view', ['record' => $campaign]));
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Could not queue campaign')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function startOver(StudentImportFileReader $reader): void
    {
        $reader->deleteStoredFile($this->storedFilePath);

        $this->reset([
            'step',
            'uploadFile',
            'storedFilePath',
            'originalFilename',
            'fileHeaders',
            'fileRows',
            'columnMapping',
            'subjectMaxMarks',
            'previewPayload',
            'importResult',
            'importError',
            'whatsappTemplateId',
            'testName',
            'limitToBatch',
            'batchId',
        ]);

        $this->step = 1;
        $this->defaultMaxMarks = 100;
        $this->academicSessionId = AcademicSession::current()?->id;
        $this->sessionDate = now()->toDateString();
        $this->columnMapping = [
            'roll_column' => null,
            'subject_columns' => [],
        ];
        $this->subjectMaxMarks = [];
    }

    public function updatedColumnMapping(): void
    {
        $this->syncSubjectMaxMarksFromMapping();
    }

    protected function syncSubjectMaxMarksFromMapping(): void
    {
        $defaults = ExamSubjectCatalog::defaultMaxMarksForColumns(
            $this->fileHeaders,
            $this->columnMapping['subject_columns'] ?? [],
            $this->defaultMaxMarks,
        );

        foreach ($defaults as $columnIndex => $maxMarks) {
            if (! array_key_exists($columnIndex, $this->subjectMaxMarks)
                && ! array_key_exists((string) $columnIndex, $this->subjectMaxMarks)) {
                $this->subjectMaxMarks[$columnIndex] = $maxMarks;
            }
        }

        $activeColumns = $this->columnMapping['subject_columns'] ?? [];

        $this->subjectMaxMarks = collect($this->subjectMaxMarks)
            ->filter(fn (mixed $value, int|string $key): bool => in_array((int) $key, $activeColumns, true))
            ->mapWithKeys(fn (mixed $value, int|string $key): array => [(int) $key => (float) $value])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function sessionOptions(): array
    {
        return AcademicSession::query()
            ->where('is_active', true)
            ->orderByDesc('starts_on')
            ->get()
            ->mapWithKeys(fn (AcademicSession $session): array => [$session->id => $session->selectLabel()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function activityTypeOptions(): array
    {
        return ActivityType::query()
            ->enabled()
            ->ordered()
            ->get()
            ->filter(fn (ActivityType $type): bool => $type->supportsScoring())
            ->mapWithKeys(fn (ActivityType $type): array => [$type->id => $type->name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function batchOptions(): array
    {
        return Batch::query()
            ->where('status', BatchStatus::Active)
            ->with('course')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Batch $batch): array => [$batch->id => $batch->selectLabel()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function whatsappTemplateOptions(): array
    {
        return WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.bulk-activity-marks-import')
                ->viewData(fn (): array => [
                    'step' => $this->step,
                    'limitToBatch' => $this->limitToBatch,
                    'sessionOptions' => $this->sessionOptions(),
                    'activityTypeOptions' => $this->activityTypeOptions(),
                    'batchOptions' => $this->batchOptions(),
                    'whatsappTemplateOptions' => $this->whatsappTemplateOptions(),
                    'fileHeaders' => $this->fileHeaders,
                    'columnMapping' => $this->columnMapping,
                    'subjectMaxMarks' => $this->subjectMaxMarks,
                    'previewPayload' => $this->previewPayload,
                    'importResult' => $this->importResult,
                    'uploadFileName' => $this->uploadFile?->getClientOriginalName(),
                    'maxRows' => StudentImportFileReader::MAX_ROWS,
                    'importError' => $this->importError,
                ]),
        ]);
    }
}
