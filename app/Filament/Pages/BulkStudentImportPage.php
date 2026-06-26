<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\StudentImportDuplicateResolution;
use App\Support\CrmAccess;
use App\Exports\StudentImportTemplateExport;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\StudentImportBatch;
use App\Services\StudentBulkImportService;
use App\Services\StudentImportColumnMapper;
use App\Services\StudentImportFileReader;
use App\Support\StudentImportFields;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

class BulkStudentImportPage extends Page
{
    use WithFileUploads;

    private const IMPORT_SESSION_KEY = 'crm.bulk_student_import';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import Students';

    protected static ?string $title = 'Import Enrolled Students';

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public function getSubheading(): ?string
    {
        return CrmHint::text('import.bulk');
    }

    public int $step = 1;

    public ?int $academicSessionId = null;

    public ?int $courseId = null;

    public ?int $batchId = null;

    public ?int $importBatchId = null;

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
     * @var array<int, string>
     */
    public array $columnMapping = [];

    /**
     * @var array<int, string>
     */
    public array $duplicateResolutions = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $importResult = null;

    public bool $isImporting = false;

    public int $importProcessed = 0;

    public int $importTotal = 0;

    /**
     * @var array{
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     preview_rejected: int,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    public array $importRunningTotals = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'preview_rejected' => 0,
        'errors' => [],
    ];

    public ?string $importError = null;

    public function mount(): void
    {
        $this->academicSessionId = AcademicSession::current()?->id;
    }

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::StudentsImport);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new StudentImportTemplateExport,
            'student-import-template.xlsx',
        );
    }

    public function goToStep(int $step, StudentImportFileReader $reader): void
    {
        if ($step === 2) {
            $this->hydrateFileFromStorage($reader);
        }

        $this->step = max(1, min(4, $step));
    }

    public function updatedAcademicSessionId(): void
    {
        $this->batchId = null;
    }

    public function updatedCourseId(): void
    {
        $this->batchId = null;
    }

    public function parseFileAndContinue(
        StudentImportFileReader $reader,
        StudentImportColumnMapper $mapper,
    ): void {
        $this->validate([
            'academicSessionId' => 'required|exists:academic_sessions,id',
            'courseId' => 'required|exists:courses,id',
            'batchId' => 'nullable|exists:batches,id',
            'uploadFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $this->discardPreviewBatch();

        if ($this->storedFilePath) {
            $reader->deleteStoredFile($this->storedFilePath);
        }

        $parsed = $reader->storeAndParse($this->uploadFile);

        $this->storedFilePath = $parsed['path'];
        $this->originalFilename = $this->uploadFile->getClientOriginalName();
        $this->fileHeaders = $parsed['headers'];
        $this->fileRows = $parsed['rows'];
        $this->columnMapping = $mapper->guess($this->fileHeaders);
        $this->duplicateResolutions = [];
        $this->importResult = null;
        $this->importError = null;
        $this->uploadFile = null;
        $this->step = 2;
        $this->persistImportSession();
    }

    public function buildPreview(
        StudentBulkImportService $importService,
        StudentImportFileReader $reader,
    ): void {
        $this->restoreImportSession();
        $this->persistImportSession();

        $this->validate([
            'academicSessionId' => 'required|exists:academic_sessions,id',
            'courseId' => 'required|exists:courses,id',
            'batchId' => 'nullable|exists:batches,id',
        ]);

        $missing = app(StudentImportColumnMapper::class)->missingRequiredFields($this->columnMapping);

        if ($missing !== []) {
            Notification::make()
                ->title('Column mapping incomplete')
                ->body('Map: '.collect($missing)->map(fn (string $field): string => StudentImportFields::labels()[$field])->join(', '))
                ->danger()
                ->send();

            return;
        }

        try {
            $rows = $this->resolveImportRows($reader);

            if ($rows === []) {
                Notification::make()
                    ->title('Spreadsheet data missing')
                    ->body('Upload the file again from step 1 — the server could not read the stored copy.')
                    ->warning()
                    ->send();

                return;
            }

            $preview = $importService->buildPreview($this->columnMapping, $rows);

            foreach ($preview as $row) {
                if (($row['status'] ?? '') === 'duplicate') {
                    $this->duplicateResolutions[(int) $row['row_number']] = StudentImportDuplicateResolution::KeepExisting->value;
                }
            }

            $session = AcademicSession::query()->findOrFail($this->academicSessionId);
            $course = Course::query()->findOrFail($this->courseId);
            $batch = $this->batchId ? Batch::query()->find($this->batchId) : null;

            $this->discardPreviewBatch();

            $previewBatch = $importService->storePreviewBatch(
                Auth::user(),
                $session,
                $course,
                $batch,
                $this->originalFilename,
                $preview,
            );

            $this->importBatchId = $previewBatch->id;
            $this->fileRows = [];
            $this->fileHeaders = [];
            $this->step = 3;
        } catch (\Throwable $exception) {
            report($exception);

            $message = $exception->getMessage();

            if (filled($this->storedFilePath) && ! Storage::disk('local')->exists($this->storedFilePath)) {
                $message = 'Uploaded file not found on server. Please go back to step 1 and upload again.';
            }

            Notification::make()
                ->title('Preview failed')
                ->body($message !== '' ? $message : 'Could not read the spreadsheet. Try uploading again or use the CSV template.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function runImport(StudentBulkImportService $importService): void
    {
        $previewBatch = $this->resolvePreviewBatch();

        if (! $previewBatch) {
            $this->resetImportProgress();

            Notification::make()
                ->title('Preview expired')
                ->body('Go back and preview the file again before importing.')
                ->warning()
                ->send();

            return;
        }

        $preview = $previewBatch->preview_rows ?? [];

        if ($this->importTotal === 0) {
            $importableCount = $importService->countImportableRows($preview, $this->duplicateResolutions);

            if ($importableCount === 0) {
                Notification::make()
                    ->title('Nothing to import')
                    ->body('Fix validation errors or choose duplicate handling before importing.')
                    ->warning()
                    ->send();

                return;
            }

            $this->importTotal = $importableCount;
            $this->importProcessed = 0;
            $this->importRunningTotals = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'preview_rejected' => 0,
                'errors' => [],
            ];
        }

        $this->isImporting = true;
        $this->importError = null;

        @set_time_limit(300);

        try {
            $session = $previewBatch->academicSession ?? AcademicSession::query()->findOrFail($this->academicSessionId);
            $course = $previewBatch->course ?? Course::query()->findOrFail($this->courseId);
            $batch = $previewBatch->batch;

            $chunk = null;
            $chunksThisRequest = 0;
            $maxChunksPerRequest = (int) ceil($this->importTotal / StudentBulkImportService::IMPORT_CHUNK_SIZE);

            do {
                $chunk = $importService->importChunk(
                    Auth::user(),
                    $session,
                    $course,
                    $batch,
                    $previewBatch,
                    $preview,
                    $this->duplicateResolutions,
                    $this->importProcessed,
                );

                $this->importProcessed += $chunk['processed'];
                $this->importRunningTotals['created'] += $chunk['created'];
                $this->importRunningTotals['updated'] += $chunk['updated'];
                $this->importRunningTotals['failed'] += $chunk['failed'];
                $this->importRunningTotals['errors'] = array_merge(
                    $this->importRunningTotals['errors'],
                    $chunk['errors'],
                );

                $chunksThisRequest++;
            } while (! $chunk['done'] && $chunksThisRequest < $maxChunksPerRequest);

            if (! $chunk['done']) {
                $this->js('setTimeout(() => $wire.runImport(), 100)');

                return;
            }

            $this->importRunningTotals['skipped'] = $chunk['skipped'];
            $this->importRunningTotals['preview_rejected'] = $chunk['preview_rejected'];

            $previewBatch->update([
                'preview_rows' => null,
                'duplicate_resolutions' => null,
                'skipped_count' => $this->importRunningTotals['skipped'],
                'status' => 'completed',
            ]);

            $result = [
                'batch_id' => $previewBatch->id,
                'created' => $this->importRunningTotals['created'],
                'updated' => $this->importRunningTotals['updated'],
                'skipped' => $this->importRunningTotals['skipped'],
                'failed' => $this->importRunningTotals['failed'],
                'preview_rejected' => $this->importRunningTotals['preview_rejected'],
                'errors' => $this->importRunningTotals['errors'],
            ];

            $this->importResult = $result;
            $this->importBatchId = null;
            $this->duplicateResolutions = [];
            $this->columnMapping = [];
            $this->step = 4;

            $imported = ($result['created'] ?? 0) + ($result['updated'] ?? 0);
            $body = "{$imported} student(s) imported successfully.";

            if (($result['preview_rejected'] ?? 0) > 0) {
                $body .= ' '.($result['preview_rejected']).' row(s) were skipped from the file due to validation issues.';
            }

            if (($result['failed'] ?? 0) > 0) {
                $body .= ' '.($result['failed']).' row(s) failed during import — see details below.';
            }

            Notification::make()
                ->title($imported > 0 ? 'Import complete' : 'Import finished with issues')
                ->body($body)
                ->success()
                ->duration(10000)
                ->send();
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Import could not start.';
            $this->importError = $message;

            Notification::make()
                ->title('Import blocked')
                ->body($message)
                ->danger()
                ->duration(10000)
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            $this->importError = config('app.debug')
                ? $exception->getMessage()
                : 'Import failed unexpectedly. If the list is large, try again or import in smaller batches.';

            Notification::make()
                ->title('Import failed')
                ->body($this->importError)
                ->danger()
                ->persistent()
                ->send();
        } finally {
            if ($this->step === 4 || filled($this->importError)) {
                $this->resetImportProgress();
            }
        }
    }

    public function importProgressPercent(): int
    {
        if ($this->importTotal <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->importProcessed / $this->importTotal) * 100));
    }

    protected function resetImportProgress(): void
    {
        $this->isImporting = false;
        $this->importProcessed = 0;
        $this->importTotal = 0;
        $this->importRunningTotals = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'preview_rejected' => 0,
            'errors' => [],
        ];
    }

    public function importableCount(StudentBulkImportService $importService): int
    {
        $preview = $this->previewRowsForDisplay();

        return $importService->countImportableRows($preview, $this->duplicateResolutions);
    }

    public function startOver(StudentImportFileReader $reader): void
    {
        $this->restoreImportSession();
        $reader->deleteStoredFile($this->storedFilePath);
        $this->discardPreviewBatch();
        $this->clearImportSession();

        $this->reset([
            'step',
            'uploadFile',
            'storedFilePath',
            'originalFilename',
            'fileHeaders',
            'fileRows',
            'columnMapping',
            'duplicateResolutions',
            'importResult',
            'importBatchId',
            'importError',
            'importProcessed',
            'importTotal',
            'importRunningTotals',
            'isImporting',
        ]);

        $this->step = 1;
        $this->academicSessionId = AcademicSession::current()?->id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function previewRowsForDisplay(): array
    {
        if (! $this->importBatchId) {
            return [];
        }

        return StudentImportBatch::query()
            ->whereKey($this->importBatchId)
            ->where('status', 'preview')
            ->value('preview_rows') ?? [];
    }

    protected function resolvePreviewBatch(): ?StudentImportBatch
    {
        if (! $this->importBatchId) {
            return null;
        }

        return StudentImportBatch::query()
            ->with(['academicSession', 'course', 'batch'])
            ->whereKey($this->importBatchId)
            ->whereIn('status', ['preview', 'processing'])
            ->first();
    }

    protected function discardPreviewBatch(): void
    {
        if (! $this->importBatchId) {
            return;
        }

        StudentImportBatch::query()
            ->whereKey($this->importBatchId)
            ->where('status', 'preview')
            ->delete();

        $this->importBatchId = null;
    }

    protected function hydrateFileFromStorage(StudentImportFileReader $reader): void
    {
        $this->restoreImportSession();

        $absolutePath = $this->storedFileAbsolutePath();

        if ($absolutePath === null) {
            return;
        }

        $parsed = $reader->parse($absolutePath);
        $this->fileHeaders = $parsed['headers'];
        $this->fileRows = $parsed['rows'];
    }

    /**
     * @return list<list<string|null>>
     */
    protected function resolveImportRows(StudentImportFileReader $reader): array
    {
        $this->restoreImportSession();

        $absolutePath = $this->storedFileAbsolutePath();

        if ($absolutePath !== null) {
            return $reader->parse($absolutePath)['rows'];
        }

        return $this->fileRows;
    }

    protected function storedFileAbsolutePath(): ?string
    {
        if (! filled($this->storedFilePath)) {
            return null;
        }

        if (! Storage::disk('local')->exists($this->storedFilePath)) {
            return null;
        }

        return Storage::disk('local')->path($this->storedFilePath);
    }

    protected function persistImportSession(): void
    {
        session([
            self::IMPORT_SESSION_KEY => [
                'user_id' => Auth::id(),
                'stored_file_path' => $this->storedFilePath,
                'original_filename' => $this->originalFilename,
                'academic_session_id' => $this->academicSessionId,
                'course_id' => $this->courseId,
                'batch_id' => $this->batchId,
                'column_mapping' => $this->columnMapping,
            ],
        ]);
    }

    protected function restoreImportSession(): void
    {
        $data = session(self::IMPORT_SESSION_KEY);

        if (! is_array($data) || ($data['user_id'] ?? null) !== Auth::id()) {
            return;
        }

        if (filled($data['stored_file_path'] ?? null)) {
            $this->storedFilePath = (string) $data['stored_file_path'];
        }

        if (filled($data['original_filename'] ?? null)) {
            $this->originalFilename = (string) $data['original_filename'];
        }

        $this->academicSessionId = $data['academic_session_id'] ?? $this->academicSessionId;
        $this->courseId = $data['course_id'] ?? $this->courseId;
        $this->batchId = $data['batch_id'] ?? $this->batchId;

        if ($this->columnMapping === [] && is_array($data['column_mapping'] ?? null)) {
            $this->columnMapping = $data['column_mapping'];
        }
    }

    protected function clearImportSession(): void
    {
        session()->forget(self::IMPORT_SESSION_KEY);
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
    public function courseOptions(): array
    {
        return InstituteProfile::activeCourseOptions(excludeUndecided: true);
    }

    /**
     * @return array<int, string>
     */
    public function batchOptions(): array
    {
        if (! $this->courseId || ! $this->academicSessionId) {
            return [];
        }

        return Batch::query()
            ->where('course_id', $this->courseId)
            ->where('academic_session_id', $this->academicSessionId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.bulk-student-import')
                ->viewData(fn (): array => [
                    'step' => $this->step,
                    'sessionOptions' => $this->sessionOptions(),
                    'courseOptions' => $this->courseOptions(),
                    'batchOptions' => $this->batchOptions(),
                    'fileHeaders' => $this->fileHeaders,
                    'fileRows' => $this->fileRows,
                    'columnMapping' => $this->columnMapping,
                    'fieldLabels' => StudentImportFields::labels(),
                    'previewRows' => $this->previewRowsForDisplay(),
                    'duplicateResolutions' => $this->duplicateResolutions,
                    'duplicateOptions' => StudentImportDuplicateResolution::cases(),
                    'importResult' => $this->importResult,
                    'uploadFileName' => $this->uploadFile?->getClientOriginalName(),
                    'maxRows' => StudentImportFileReader::MAX_ROWS,
                    'importError' => $this->importError,
                    'isImporting' => $this->isImporting,
                    'importProcessed' => $this->importProcessed,
                    'importTotal' => $this->importTotal,
                    'importProgressPercent' => $this->importProgressPercent(),
                    'importableCount' => $this->importableCount(app(StudentBulkImportService::class)),
                ]),
        ]);
    }
}
