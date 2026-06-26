<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\NumberSequenceType;
use App\Enums\StudentImportDuplicateResolution;
use App\Enums\ProgrammeCategory;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\StudentImportBatch;
use App\Models\User;
use App\Models\Visit;
use App\Support\CrmCacheInvalidator;
use App\Support\IndianMobileNumber;
use App\Support\StudentImportFields;
use App\Support\MeetingForOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentBulkImportService
{
    public const IMPORT_CHUNK_SIZE = 20;

    public function __construct(
        protected EnrollmentRollNumberService $rollNumbers,
        protected BatchService $batches,
        protected FeeStructureService $feeStructures,
        protected NumberGeneratorService $numberGenerator,
        protected AuditService $audit,
        protected StudentAuthService $studentAuth,
        protected StudentMobileService $mobiles,
        protected StudentImportBatchResolver $batchResolver,
    ) {}

    /**
     * @param  array<int, string>  $columnMapping
     * @param  list<list<string|null>>  $rows
     * @return list<array<string, mixed>>
     */
    public function buildPreview(array $columnMapping, array $rows, ?int $academicSessionScopeId = null): array
    {
        $preview = [];
        $seenRolls = [];
        $seenMobiles = [];
        $rollKeys = [];
        $mobileKeys = [];

        foreach ($rows as $index => $row) {
            $data = $this->mapRow($columnMapping, $row);
            $rollKey = strtoupper(trim((string) ($data[StudentImportFields::ROLL_NUMBER] ?? '')));
            $mobileKey = IndianMobileNumber::normalizeFromSpreadsheet($data[StudentImportFields::MOBILE] ?? null) ?? '';

            if ($rollKey !== '') {
                $rollKeys[] = $rollKey;
            }

            if ($mobileKey !== '') {
                $mobileKeys[] = $mobileKey;
            }
        }

        $existingRolls = $rollKeys === []
            ? collect()
            : Enrollment::query()
                ->whereIn('enrollment_number', array_values(array_unique($rollKeys)))
                ->pluck('enrollment_number')
                ->map(fn (string $roll): string => strtoupper(trim($roll)))
                ->flip();

        $existingStudentsByMobile = $mobileKeys === []
            ? collect()
            : collect($mobileKeys)
                ->mapWithKeys(function (string $mobileKey): array {
                    $student = $this->mobiles->findStudentByNumber($mobileKey);

                    return $student ? [$mobileKey => $student] : [];
                });

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->mapRow($columnMapping, $row);
            $warnings = $this->mobileImportWarnings($data);
            $errors = $this->validateRowData($data);
            $data = $this->stripImportMeta($data);

            $rollKey = strtoupper(trim((string) ($data[StudentImportFields::ROLL_NUMBER] ?? '')));
            $mobileKey = IndianMobileNumber::normalizeFromSpreadsheet($data[StudentImportFields::MOBILE] ?? null) ?? '';

            if ($rollKey !== '' && isset($seenRolls[$rollKey])) {
                $errors[] = "Duplicate roll number in file (also on row {$seenRolls[$rollKey]}).";
            } elseif ($rollKey !== '') {
                $seenRolls[$rollKey] = $rowNumber;
            }

            if ($mobileKey !== '' && ! isset($seenMobiles[$mobileKey])) {
                $seenMobiles[$mobileKey] = $rowNumber;
            }

            if ($rollKey !== '' && $existingRolls->has($rollKey)) {
                $errors[] = 'Roll number is already assigned to another student.';
            }

            $existingStudent = $mobileKey !== ''
                ? $existingStudentsByMobile->get($mobileKey)
                : null;

            $resolvedBatch = null;
            $batchLabel = trim((string) ($data[StudentImportFields::BATCH_SECTION] ?? ''));

            if ($batchLabel !== '' && $errors === []) {
                $resolvedBatch = $this->batchResolver->resolve($batchLabel, $academicSessionScopeId);

                if (! $resolvedBatch) {
                    $suggestions = $this->batchResolver->suggestions($batchLabel, $academicSessionScopeId);
                    $message = "No CRM batch matches “{$batchLabel}”. Create it under Academics → Batches first.";

                    if ($suggestions !== []) {
                        $message .= ' Did you mean: '.implode(', ', $suggestions).'?';
                    }

                    $errors[] = $message;
                }
            }

            $status = 'ready';

            if ($errors !== []) {
                $status = 'error';
            } elseif ($existingStudent) {
                $status = 'duplicate';
            }

            $preview[] = [
                'row_number' => $rowNumber,
                'data' => $data,
                'status' => $status,
                'errors' => $errors,
                'warnings' => $warnings,
                'resolved_batch' => $resolvedBatch ? [
                    'id' => $resolvedBatch->id,
                    'name' => $resolvedBatch->name,
                    'course_id' => $resolvedBatch->course_id,
                    'course_name' => $resolvedBatch->course?->name,
                    'session_id' => $resolvedBatch->academic_session_id,
                    'session_name' => $resolvedBatch->academicSession?->name,
                ] : null,
                'existing_student' => $existingStudent ? [
                    'id' => $existingStudent->id,
                    'name' => $existingStudent->name,
                    'father_name' => $existingStudent->father_name,
                    'mobile' => $existingStudent->mobile,
                    'roll_number' => $existingStudent->activeEnrollment?->enrollment_number,
                ] : null,
            ];
        }

        return $this->applyDuplicateMobileImportPolicy($preview);
    }

    /**
     * Duplicate mobiles in the spreadsheet import without a number so staff can fix later.
     *
     * @param  list<array<string, mixed>>  $preview
     * @return list<array<string, mixed>>
     */
    protected function applyDuplicateMobileImportPolicy(array $preview): array
    {
        $mobileIndexes = [];

        foreach ($preview as $index => $row) {
            $mobile = trim((string) ($row['data'][StudentImportFields::MOBILE] ?? ''));

            if ($mobile !== '' && preg_match('/^[6-9]\d{9}$/', $mobile)) {
                $mobileIndexes[$mobile][] = $index;
            }
        }

        foreach ($mobileIndexes as $indices) {
            if (count($indices) < 2) {
                continue;
            }

            $rowNumbers = array_map(
                fn (int $index): int => (int) $preview[$index]['row_number'],
                $indices,
            );

            foreach ($indices as $index) {
                if (($preview[$index]['status'] ?? '') === 'error') {
                    continue;
                }

                $rowNumber = (int) $preview[$index]['row_number'];
                $otherRows = array_values(array_filter(
                    $rowNumbers,
                    fn (int $number): bool => $number !== $rowNumber,
                ));

                $preview[$index]['data'][StudentImportFields::MOBILE] = '';
                $preview[$index]['warnings'] = array_values(array_unique(array_merge(
                    $preview[$index]['warnings'] ?? [],
                    ['Duplicate mobile in file (also on row '.implode(', ', $otherRows).') — importing without mobile; add from profile later.'],
                )));
            }
        }

        return $preview;
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     * @param  array<int, string>  $duplicateResolutions row_number => resolution value
     * @return array{
     *     batch_id: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     preview_rejected: int,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    public function import(
        User $staff,
        string $originalFilename,
        array $preview,
        array $duplicateResolutions,
        ?StudentImportBatch $existingBatch = null,
        ?int $academicSessionScopeId = null,
    ): array {
        $batch = $this->prepareImportBatch(
            $staff,
            $academicSessionScopeId,
            $originalFilename,
            $preview,
            $duplicateResolutions,
            $existingBatch,
        );

        $totals = $this->emptyImportTotals();
        $offset = 0;

        do {
            $chunk = $this->importChunk(
                $staff,
                $batch,
                $preview,
                $duplicateResolutions,
                $offset,
            );

            $this->mergeImportTotals($totals, $chunk);
            $offset += $chunk['processed'];
        } while (! $chunk['done']);

        return $this->finalizeImportBatch($batch, $preview, $duplicateResolutions, $totals);
    }

    /**
     * Process the next slice of importable preview rows (for chunked Livewire imports).
     *
     * @param  list<array<string, mixed>>  $preview
     * @param  array<int, string>  $duplicateResolutions
     * @return array{
     *     processed: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     preview_rejected: int,
     *     errors: list<array{row: int, message: string}>,
     *     done: bool
     * }
     */
    public function importChunk(
        User $staff,
        StudentImportBatch $batch,
        array $preview,
        array $duplicateResolutions,
        int $offset,
        int $limit = self::IMPORT_CHUNK_SIZE,
    ): array {
        if ($offset === 0) {
            $batch->update([
                'duplicate_resolutions' => $duplicateResolutions,
                'total_rows' => count($preview),
                'status' => 'processing',
            ]);
        }

        $importableRows = $this->importablePreviewRows($preview, $duplicateResolutions);
        $slice = array_slice($importableRows, $offset, $limit);

        $existingStudentIds = collect($slice)
            ->pluck('existing_student.id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingStudents = $existingStudentIds === []
            ? collect()
            : Student::query()->whereIn('id', $existingStudentIds)->get()->keyBy('id');

        $batchIds = collect($slice)
            ->pluck('resolved_batch.id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $batchesById = $batchIds === []
            ? collect()
            : Batch::query()
                ->with(['course', 'academicSession'])
                ->whereIn('id', $batchIds)
                ->get()
                ->keyBy('id');

        $chunk = $this->emptyImportTotals();
        $chunk['processed'] = count($slice);

        foreach ($slice as $item) {
            $rowNumber = (int) $item['row_number'];
            $data = $item['data'];
            $existingStudent = isset($item['existing_student']['id'])
                ? $existingStudents->get($item['existing_student']['id'])
                : null;
            $resolvedBatchId = $item['resolved_batch']['id'] ?? null;
            $rowBatch = $resolvedBatchId ? $batchesById->get($resolvedBatchId) : null;

            try {
                if (! $rowBatch) {
                    throw ValidationException::withMessages([
                        'batch' => 'Batch could not be resolved for this row. Preview the file again.',
                    ]);
                }

                $result = DB::transaction(function () use (
                    $staff,
                    $batch,
                    $data,
                    $rowBatch,
                    $existingStudent,
                    $item,
                ): string {
                    return $this->importRow(
                        $staff,
                        $batch,
                        $data,
                        $rowBatch,
                        $existingStudent,
                        $item['warnings'] ?? [],
                    );
                });

                if ($result === 'created') {
                    $chunk['created']++;
                } else {
                    $chunk['updated']++;
                }

                if ($this->resolvedImportMobile($data) === null) {
                    $chunk['without_mobile']++;
                }
            } catch (\Throwable $exception) {
                $chunk['failed']++;
                $chunk['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $exception instanceof ValidationException
                        ? collect($exception->errors())->flatten()->first() ?? 'Validation failed.'
                        : $exception->getMessage(),
                ];
            }
        }

        $batch->update([
            'created_count' => ($batch->created_count ?? 0) + $chunk['created'],
            'updated_count' => ($batch->updated_count ?? 0) + $chunk['updated'],
            'failed_count' => ($batch->failed_count ?? 0) + $chunk['failed'],
            'error_rows' => array_merge($batch->error_rows ?? [], $chunk['errors']),
        ]);

        $chunk['done'] = ($offset + $chunk['processed']) >= count($importableRows);

        if ($chunk['done']) {
            $chunk['skipped'] = $this->countSkippedDuplicates($preview, $duplicateResolutions);
            $chunk['preview_rejected'] = $this->countPreviewRejected($preview);
        }

        return $chunk;
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     * @param  array<int, string>  $duplicateResolutions
     * @return array{
     *     batch_id: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     preview_rejected: int,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    protected function finalizeImportBatch(
        StudentImportBatch $batch,
        array $preview,
        array $duplicateResolutions,
        array $totals,
    ): array {
        $totals['skipped'] = $this->countSkippedDuplicates($preview, $duplicateResolutions);
        $totals['preview_rejected'] = $this->countPreviewRejected($preview);

        $batch->update([
            'created_count' => $totals['created'],
            'updated_count' => $totals['updated'],
            'skipped_count' => $totals['skipped'],
            'failed_count' => $totals['failed'],
            'error_rows' => $totals['errors'],
            'preview_rows' => null,
            'duplicate_resolutions' => null,
            'status' => 'completed',
        ]);

        if (($totals['created'] + $totals['updated']) > 0) {
            CrmCacheInvalidator::afterBulkImport();
        }

        return [
            'batch_id' => $batch->id,
            'created' => $totals['created'],
            'updated' => $totals['updated'],
            'skipped' => $totals['skipped'],
            'failed' => $totals['failed'],
            'preview_rejected' => $totals['preview_rejected'],
            'without_mobile' => $totals['without_mobile'] ?? 0,
            'errors' => $totals['errors'],
        ];
    }

    protected function prepareImportBatch(
        User $staff,
        ?int $academicSessionScopeId,
        string $originalFilename,
        array $preview,
        array $duplicateResolutions,
        ?StudentImportBatch $existingBatch,
    ): StudentImportBatch {
        if ($existingBatch) {
            return $existingBatch;
        }

        return StudentImportBatch::query()->create([
            'user_id' => $staff->id,
            'academic_session_id' => $academicSessionScopeId,
            'course_id' => null,
            'batch_id' => null,
            'original_filename' => $originalFilename,
            'total_rows' => count($preview),
            'status' => 'processing',
            'duplicate_resolutions' => $duplicateResolutions,
        ]);
    }

    /**
     * @return array{
     *     processed: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     preview_rejected: int,
     *     errors: list<array{row: int, message: string}>,
     *     done: bool
     * }
     */
    protected function emptyImportTotals(): array
    {
        return [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'preview_rejected' => 0,
            'without_mobile' => 0,
            'errors' => [],
            'done' => false,
        ];
    }

    /**
     * @param  array{
     *     processed: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     preview_rejected: int,
     *     errors: list<array{row: int, message: string}>,
     *     done: bool
     * }  $totals
     * @param  array{
     *     processed: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     preview_rejected: int,
     *     errors: list<array{row: int, message: string}>,
     *     done: bool
     * }  $chunk
     */
    protected function mergeImportTotals(array &$totals, array $chunk): void
    {
        $totals['created'] += $chunk['created'];
        $totals['updated'] += $chunk['updated'];
        $totals['failed'] += $chunk['failed'];
        $totals['without_mobile'] += $chunk['without_mobile'] ?? 0;
        $totals['errors'] = array_merge($totals['errors'], $chunk['errors']);

        if ($chunk['done']) {
            $totals['skipped'] = $chunk['skipped'];
            $totals['preview_rejected'] = $chunk['preview_rejected'];
            $totals['done'] = true;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     * @param  array<int, string>  $duplicateResolutions
     * @return list<array<string, mixed>>
     */
    protected function importablePreviewRows(array $preview, array $duplicateResolutions): array
    {
        return collect($preview)
            ->filter(fn (array $row): bool => $this->shouldImportRow($row, $duplicateResolutions))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $duplicateResolutions
     */
    protected function shouldImportRow(array $row, array $duplicateResolutions): bool
    {
        if (($row['status'] ?? '') === 'ready') {
            return true;
        }

        if (($row['status'] ?? '') !== 'duplicate') {
            return false;
        }

        return ($duplicateResolutions[$row['row_number']] ?? StudentImportDuplicateResolution::KeepExisting->value)
            === StudentImportDuplicateResolution::UseFile->value;
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     * @param  array<int, string>  $duplicateResolutions
     */
    protected function countSkippedDuplicates(array $preview, array $duplicateResolutions): int
    {
        return collect($preview)
            ->filter(function (array $row) use ($duplicateResolutions): bool {
                if (($row['status'] ?? '') !== 'duplicate') {
                    return false;
                }

                return ($duplicateResolutions[$row['row_number']] ?? StudentImportDuplicateResolution::KeepExisting->value)
                    === StudentImportDuplicateResolution::KeepExisting->value;
            })
            ->count();
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     */
    protected function countPreviewRejected(array $preview): int
    {
        return collect($preview)->where('status', 'error')->count();
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     */
    public function storePreviewBatch(
        User $staff,
        ?int $academicSessionScopeId,
        string $originalFilename,
        array $preview,
    ): StudentImportBatch {
        return StudentImportBatch::query()->create([
            'user_id' => $staff->id,
            'academic_session_id' => $academicSessionScopeId,
            'course_id' => null,
            'batch_id' => null,
            'original_filename' => $originalFilename,
            'preview_rows' => $preview,
            'total_rows' => count($preview),
            'status' => 'preview',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     * @param  array<int, string>  $duplicateResolutions
     */
    public function countImportableRows(array $preview, array $duplicateResolutions): int
    {
        return count($this->importablePreviewRows($preview, $duplicateResolutions));
    }

    /**
     * @param  array<int, string>  $columnMapping
     * @param  list<string|null>  $row
     * @return array<string, mixed>
     */
    public function mapRow(array $columnMapping, array $row): array
    {
        $data = [];

        foreach ($columnMapping as $index => $field) {
            if ($field === StudentImportFields::SKIP) {
                continue;
            }

            $data[$field] = $row[$index] ?? null;
        }

        if (filled($data[StudentImportFields::ROLL_NUMBER] ?? null)) {
            $data[StudentImportFields::ROLL_NUMBER] = strtoupper(trim((string) $data[StudentImportFields::ROLL_NUMBER]));
        }

        if (filled($data[StudentImportFields::BATCH_SECTION] ?? null)) {
            $data[StudentImportFields::BATCH_SECTION] = trim((string) $data[StudentImportFields::BATCH_SECTION]);
        }

        if (filled($data[StudentImportFields::MOBILE] ?? null)) {
            $rawMobile = $data[StudentImportFields::MOBILE];
            $data['_import_mobile_had_raw'] = true;

            if (IndianMobileNumber::isLossyScientificNotation(
                is_scalar($rawMobile) ? trim((string) $rawMobile) : null,
            )) {
                $data['_import_mobile_error'] = 'scientific_notation';
                $data[StudentImportFields::MOBILE] = '';
            } else {
                $normalized = IndianMobileNumber::normalizeFromSpreadsheet($rawMobile);

                if ($normalized === null) {
                    $data['_import_mobile_error'] = 'invalid';
                    $data[StudentImportFields::MOBILE] = '';
                } else {
                    $data[StudentImportFields::MOBILE] = $normalized;
                }
            }
        } else {
            $data['_import_mobile_had_raw'] = false;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function validateRowData(array $data): array
    {
        $errors = [];

        foreach (StudentImportFields::required() as $field) {
            if (blank($data[$field] ?? null)) {
                $errors[] = StudentImportFields::labels()[$field].' is required.';
            }
        }

        $roll = (string) ($data[StudentImportFields::ROLL_NUMBER] ?? '');

        if ($roll !== '' && strlen($roll) > 50) {
            $errors[] = 'Roll number must be 50 characters or fewer.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function mobileImportWarnings(array $data): array
    {
        $warnings = [];
        $mobile = trim((string) ($data[StudentImportFields::MOBILE] ?? ''));
        $error = $data['_import_mobile_error'] ?? null;

        if ($error === 'scientific_notation') {
            $warnings[] = 'Mobile unreadable (Excel scientific format) — importing without mobile.';
        } elseif ($mobile === '') {
            if ($data['_import_mobile_had_raw'] ?? false) {
                $warnings[] = 'Mobile invalid — importing without mobile.';
            } else {
                $warnings[] = 'No mobile — importing without mobile; add from student profile later.';
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripImportMeta(array $data): array
    {
        unset($data['_import_mobile_error'], $data['_import_mobile_had_raw']);

        return $data;
    }

    protected function resolvedImportMobile(array $data): ?string
    {
        $mobile = trim((string) ($data[StudentImportFields::MOBILE] ?? ''));

        return preg_match('/^[6-9]\d{9}$/', $mobile) ? $mobile : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function importRow(
        User $staff,
        StudentImportBatch $importBatch,
        array $data,
        Batch $batch,
        ?Student $existingStudent,
        array $mobileWarnings = [],
    ): string {
        $course = $batch->course ?? Course::query()->findOrFail($batch->course_id);
        $session = $batch->academicSession ?? AcademicSession::query()->findOrFail($batch->academic_session_id);

        $created = false;
        $student = $existingStudent;

        if (! $student) {
            $student = Student::query()->create($this->studentAttributes($data, null, $mobileWarnings));
            $created = true;
        } else {
            $student->update($this->studentAttributes($data, $student, $mobileWarnings));
        }

        if ($student->activeEnrollment) {
            $enrollment = $student->activeEnrollment;

            if ($enrollment->course_id !== $course->id || $enrollment->academic_session_id !== $session->id) {
                throw ValidationException::withMessages([
                    'course' => 'Student is already enrolled in a different course or session. Update from the student profile instead.',
                ]);
            }

            $this->rollNumbers->update(
                $enrollment,
                (string) $data[StudentImportFields::ROLL_NUMBER],
                $staff,
            );

            $this->batches->assign($student, $batch, $staff);

            return $created ? 'created' : 'updated';
        }

        if ($student->admissions()->exists()) {
            throw ValidationException::withMessages([
                'student' => 'Student already has an admission on file. Open the profile to complete enrollment manually.',
            ]);
        }

        $enquiry = $student->enquiries()->first();

        if (! $enquiry) {
            $enquiry = $this->createEnquiry($student, $course, $staff);
        } else {
            $enquiry->update(['course_id' => $course->id]);
        }

        $this->createEnrolledRecords(
            $student,
            $enquiry,
            $course,
            $session,
            $importBatch,
            (string) $data[StudentImportFields::ROLL_NUMBER],
            $staff,
        );

        $this->batches->assign($student, $batch, $staff);

        return $created ? 'created' : 'updated';
    }

    protected function createEnquiry(Student $student, Course $course, User $staff): Enquiry
    {
        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => $this->numberGenerator->generate(NumberSequenceType::Enquiry),
            'course_id' => $course->id,
            'lead_source' => LeadSource::BulkImport,
            'meeting_for' => MeetingForOptions::defaultValue(),
            'visit_type' => VisitType::FirstVisit,
            'latest_visit_status' => VisitStatus::Joined,
            'meeting_with_user_id' => null,
        ]);

        Visit::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => now()->toDateString(),
            'staff_user_id' => $staff->id,
            'discussion_summary' => 'Bulk import — enrolled student',
            'remarks' => 'Imported via Students & Admissions → Import Students',
            'status' => VisitStatus::Joined,
        ]);

        return $enquiry;
    }

    protected function createEnrolledRecords(
        Student $student,
        Enquiry $enquiry,
        Course $course,
        AcademicSession $session,
        StudentImportBatch $importBatch,
        string $rollNumber,
        User $staff,
    ): Enrollment {
        $courseFee = $this->resolvedCourseFee($course);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'import_batch_id' => $importBatch->id,
            'admission_number' => $this->numberGenerator->generate(NumberSequenceType::Admission),
            'course_fee' => $courseFee,
            'discount_amount' => 0,
            'net_fee' => $courseFee,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_by_user_id' => $staff->id,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => strtoupper(trim($rollNumber)),
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        $student->update(['status' => StudentStatus::Enrolled]);
        $this->studentAuth->ensurePortalLoginForStudent($student);
        $enquiry->update(['latest_visit_status' => VisitStatus::Joined]);

        $this->feeStructures->createFromAdmission($enrollment, $admission, $staff);

        $this->audit->log(
            action: 'Bulk Import Enrollment',
            auditable: $enrollment,
            newValues: [
                'enrollment_number' => $enrollment->enrollment_number,
                'academic_session_id' => $session->id,
                'course_id' => $course->id,
                'import_batch_id' => $importBatch->id,
            ],
            user: $staff,
        );

        return $enrollment;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function studentAttributes(array $data, ?Student $existing = null, array $mobileWarnings = []): array
    {
        $attributes = [
            'name' => trim((string) $data[StudentImportFields::NAME]),
        ];

        if (filled($data[StudentImportFields::FATHER_NAME] ?? null)) {
            $attributes['father_name'] = trim((string) $data[StudentImportFields::FATHER_NAME]);
        } elseif (! $existing) {
            $attributes['father_name'] = null;
        }

        $dateOfBirth = $this->parseOptionalDate($data[StudentImportFields::DATE_OF_BIRTH] ?? null);

        if ($dateOfBirth) {
            $attributes['date_of_birth'] = $dateOfBirth;
        }

        $gender = filled($data[StudentImportFields::GENDER] ?? null)
            ? $this->parseGender((string) $data[StudentImportFields::GENDER])
            : null;

        if ($gender) {
            $attributes['gender'] = $gender;
        }

        if (! $existing) {
            $attributes['mobile'] = $this->resolvedImportMobile($data);
            $attributes['status'] = StudentStatus::Enquiry;
            $attributes['portal_password'] = $this->studentAuth->hashForNewStudent();
        } elseif ($this->resolvedImportMobile($data) !== null) {
            $attributes['mobile'] = $this->resolvedImportMobile($data);
            $attributes['mobile_import_note'] = null;
        } elseif (blank($existing->portal_password)) {
            $attributes['portal_password'] = $this->studentAuth->hashForNewStudent();
        }

        if ($this->resolvedImportMobile($data) === null) {
            $note = $this->mobileImportNoteFromWarnings($mobileWarnings);

            if ($note !== null) {
                $attributes['mobile_import_note'] = $note;
            }
        }

        return $attributes;
    }

    /**
     * @param  list<string>  $warnings
     */
    protected function mobileImportNoteFromWarnings(array $warnings): ?string
    {
        $warnings = array_values(array_filter(array_map(
            fn (string $warning): string => trim($warning),
            $warnings,
        )));

        return $warnings === [] ? null : implode(' ', $warnings);
    }

    protected function parseGender(string $value): ?Gender
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'm', 'male', 'boy' => Gender::Male,
            'f', 'female', 'girl' => Gender::Female,
            'other', 'o' => Gender::Other,
            default => Gender::tryFrom($normalized),
        };
    }

    protected function resolvedCourseFee(Course $course): float
    {
        $fee = max(0, (float) ($course->fee ?? 0));

        if ($fee <= 0) {
            throw ValidationException::withMessages([
                'course' => 'This course has no fee set. Update it in Courses admin before importing.',
            ]);
        }

        return $fee;
    }

    protected function parseOptionalDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            if ($serial >= 1 && $serial <= 60000) {
                try {
                    return Carbon::createFromTimestampUTC((int) (($serial - 25569) * 86400))->toDateString();
                } catch (\Throwable) {
                    // Ignore invalid Excel serial values.
                }
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
