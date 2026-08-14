<?php

namespace App\Services;

use App\Enums\HomeworkAssignmentStatus;
use App\Enums\HomeworkContentType;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\User;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Daily homework workflow: subject teachers submit per subject, admin reviews/approves,
 * then one combined WhatsApp goes to parents listing only the subjects that have homework.
 */
class HomeworkSubmissionService
{
    public function __construct(
        protected HomeworkCheckService $scope,
        protected HomeworkWhatsAppService $whatsapp,
    ) {}

    /**
     * Classes a user may submit/review homework for (teacher = assigned classes, admin = all).
     *
     * @return array<int, string>
     */
    public function batchOptionsFor(User $user): array
    {
        return $this->scope->batchOptionsFor($user);
    }

    /**
     * Subjects the user may pick for a class. Admins/lead teachers see all class subjects.
     *
     * @return array<int, string>
     */
    public function subjectOptionsForBatch(User $user, int $batchId): array
    {
        return $this->scope->subjectOptionsForBatch($user, $batchId);
    }

    /**
     * All classes (admin review board sees every class, not only assigned ones).
     *
     * @return array<int, string>
     */
    public function allBatchOptions(): array
    {
        return Batch::query()
            ->with(['course', 'academicSession'])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Batch $batch): array => [
                $batch->id => $batch->displayLabel(),
            ])
            ->all();
    }

    /**
     * All active subjects of a class programme (used by the admin review board).
     *
     * @return array<int, string>
     */
    public function allSubjectOptionsForBatch(int $batchId): array
    {
        $batch = Batch::query()->find($batchId);

        if (! $batch?->course_id) {
            return [];
        }

        return CourseSubject::query()
            ->where('course_id', $batch->course_id)
            ->where('is_active', true)
            ->ordered()
            ->get()
            ->mapWithKeys(fn (CourseSubject $subject): array => [
                $subject->id => $subject->displayLabel(),
            ])
            ->all();
    }

    public function canSubmit(User $user): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        return BatchStaffAssignment::query()->where('user_id', $user->id)->exists();
    }

    public function normalizeDate(?string $date): string
    {
        $value = filled($date)
            ? Carbon::parse($date)->toDateString()
            : now()->toDateString();

        if ($value > now()->toDateString()) {
            throw ValidationException::withMessages([
                'homework_date' => 'Homework date cannot be in the future.',
            ]);
        }

        return $value;
    }

    /**
     * Create or update a subject's homework for a class/date.
     *
     * Teacher submit → status Submitted (awaiting admin). Admin save → status Approved (ready to send).
     *
     * @param  array{
     *     batch_id: int,
     *     course_subject_id: int,
     *     homework_date?: string|null,
     *     title: string,
     *     description: string,
     *     file_path?: string|null,
     *     clear_file?: bool
     * }  $data
     */
    public function submit(User $user, array $data, bool $asAdmin = false): HomeworkAssignment
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            throw ValidationException::withMessages([
                'batch_id' => 'Homework feature is not enabled on your licence.',
            ]);
        }

        $batchId = (int) $data['batch_id'];
        $subjectId = (int) $data['course_subject_id'];
        $date = $this->normalizeDate($data['homework_date'] ?? null);

        if (! $this->scope->userCanAccessBatch($user, $batchId)) {
            throw ValidationException::withMessages([
                'batch_id' => 'You are not assigned to this class.',
            ]);
        }

        $batch = Batch::query()->with('course')->findOrFail($batchId);
        $subject = CourseSubject::query()->findOrFail($subjectId);

        if ((int) $subject->course_id !== (int) $batch->course_id) {
            throw ValidationException::withMessages([
                'course_subject_id' => 'Subject does not belong to this class programme.',
            ]);
        }

        if (! $asAdmin && ! $user->hasRole(RoleName::SuperAdmin->value)) {
            $allowed = array_keys($this->scope->subjectOptionsForBatch($user, $batchId));

            if (! in_array($subjectId, array_map('intval', $allowed), true)) {
                throw ValidationException::withMessages([
                    'course_subject_id' => 'You are not assigned to teach this subject for this class.',
                ]);
            }
        }

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($title === '') {
            $title = $subject->displayLabel().' homework';
        }

        if ($description === '' && blank($data['file_path'] ?? null)) {
            throw ValidationException::withMessages([
                'description' => 'Add homework details or attach a PDF/image.',
            ]);
        }

        $existing = HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->where('course_subject_id', $subjectId)
            ->whereDate('homework_date', $date)
            ->first();

        $filePath = $existing?->file_path;

        if (filled($data['file_path'] ?? null)) {
            $filePath = (string) $data['file_path'];
        } elseif ((bool) ($data['clear_file'] ?? false)) {
            $filePath = null;
        }

        $contentType = HomeworkContentType::Text;

        if ($filePath !== null && Storage::disk('public')->exists($filePath)) {
            $mime = (string) Storage::disk('public')->mimeType($filePath);
            $contentType = str_starts_with($mime, 'image/')
                ? HomeworkContentType::Image
                : HomeworkContentType::Pdf;
        }

        $status = $asAdmin ? HomeworkAssignmentStatus::Approved : HomeworkAssignmentStatus::Submitted;

        $attributes = [
            'batch_id' => $batchId,
            'course_subject_id' => $subjectId,
            'homework_date' => $date,
            'title' => $title,
            'description' => $description,
            'content_type' => $contentType,
            'file_path' => $filePath,
            'status' => $status,
        ];

        if ($existing) {
            $existing->fill($attributes);

            if (! $asAdmin) {
                $existing->submitted_by_user_id = $user->id;
                $existing->submitted_at = now();
                // A teacher re-submitting after approval sends it back for review.
                $existing->approved_by_user_id = null;
                $existing->approved_at = null;
            } else {
                $existing->approved_by_user_id = $user->id;
                $existing->approved_at = now();
                $existing->submitted_by_user_id ??= $user->id;
                $existing->submitted_at ??= now();
                $existing->published_at ??= now();
            }

            $existing->save();

            return $existing->fresh(['batch', 'courseSubject']);
        }

        $assignment = new HomeworkAssignment($attributes);
        $assignment->created_by_user_id = $user->id;
        $assignment->submitted_by_user_id = $user->id;
        $assignment->submitted_at = now();

        if ($asAdmin) {
            $assignment->approved_by_user_id = $user->id;
            $assignment->approved_at = now();
            $assignment->published_at = now();
        }

        $assignment->save();

        return $assignment->fresh(['batch', 'courseSubject']);
    }

    public function approve(User $admin, int $assignmentId): HomeworkAssignment
    {
        $assignment = HomeworkAssignment::query()->findOrFail($assignmentId);

        if (blank($assignment->description) && ! $assignment->hasFile()) {
            throw ValidationException::withMessages([
                'approve' => 'Nothing to approve — this subject has no homework details or file.',
            ]);
        }

        $assignment->forceFill([
            'status' => HomeworkAssignmentStatus::Approved,
            'approved_by_user_id' => $admin->id,
            'approved_at' => now(),
            'published_at' => $assignment->published_at ?? now(),
        ])->save();

        $assignment->ensurePublicToken();

        return $assignment->fresh(['batch', 'courseSubject']);
    }

    public function deleteSubmission(User $user, int $assignmentId, bool $asAdmin = false): void
    {
        $assignment = HomeworkAssignment::query()->findOrFail($assignmentId);

        if (! $asAdmin && ! $user->hasRole(RoleName::SuperAdmin->value)) {
            if (! $this->scope->userCanAccessBatch($user, (int) $assignment->batch_id)) {
                throw ValidationException::withMessages([
                    'delete' => 'You cannot remove homework for this class.',
                ]);
            }

            if ($assignment->status === HomeworkAssignmentStatus::Sent) {
                throw ValidationException::withMessages([
                    'delete' => 'This homework was already sent to parents and cannot be removed here.',
                ]);
            }
        }

        $assignment->delete();
    }

    /**
     * Review board for one class/date: one row per subject with its submission state.
     *
     * @return array{
     *     subjects: list<array{
     *         course_subject_id: int,
     *         subject: string,
     *         assignment_id: ?int,
     *         status: ?string,
     *         status_key: ?string,
     *         status_color: ?string,
     *         title: ?string,
     *         has_file: bool,
     *         public_url: ?string,
     *         teacher: ?string
     *     }>,
     *     summary: array{total: int, submitted: int, approved: int, sent: int, missing: int}
     * }
     */
    public function boardForClassDate(User $user, int $batchId, string $date): array
    {
        $date = $this->normalizeDate($date);
        $subjectOptions = $this->allSubjectOptionsForBatch($batchId);

        $assignments = HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->whereNotNull('course_subject_id')
            ->whereDate('homework_date', $date)
            ->with(['courseSubject', 'submittedBy', 'approvedBy'])
            ->get()
            ->keyBy('course_subject_id');

        $rows = [];
        $submitted = 0;
        $approved = 0;
        $sent = 0;
        $missing = 0;

        foreach ($subjectOptions as $subjectId => $label) {
            /** @var HomeworkAssignment|null $assignment */
            $assignment = $assignments->get($subjectId);

            if ($assignment === null) {
                $missing++;
            } else {
                match ($assignment->status) {
                    HomeworkAssignmentStatus::Submitted => $submitted++,
                    HomeworkAssignmentStatus::Approved => $approved++,
                    HomeworkAssignmentStatus::Sent => $sent++,
                    default => null,
                };
            }

            $rows[] = [
                'course_subject_id' => (int) $subjectId,
                'subject' => (string) $label,
                'assignment_id' => $assignment?->id,
                'status' => $assignment?->status?->label(),
                'status_key' => $assignment?->status?->value,
                'status_color' => $assignment?->status?->color(),
                'title' => $assignment?->title,
                'has_file' => (bool) $assignment?->hasFile(),
                'public_url' => $assignment ? $assignment->publicUrl() : null,
                'teacher' => $assignment?->submittedBy?->name ?? $assignment?->createdBy?->name,
            ];
        }

        return [
            'subjects' => $rows,
            'summary' => [
                'total' => count($subjectOptions),
                'submitted' => $submitted,
                'approved' => $approved,
                'sent' => $sent,
                'missing' => $missing,
            ],
        ];
    }

    /**
     * Submissions a teacher made for a class/date (their own subjects).
     *
     * @return Collection<int, HomeworkAssignment>
     */
    public function submissionsForTeacher(User $user, int $batchId, string $date): Collection
    {
        $date = $this->normalizeDate($date);

        $query = HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->whereNotNull('course_subject_id')
            ->whereDate('homework_date', $date)
            ->with(['courseSubject', 'approvedBy']);

        if (! $user->hasRole(RoleName::SuperAdmin->value)) {
            $subjectIds = array_map('intval', array_keys($this->scope->subjectOptionsForBatch($user, $batchId)));

            if ($subjectIds === []) {
                return collect();
            }

            $query->whereIn('course_subject_id', $subjectIds);
        }

        return $query->orderBy('course_subject_id')->get();
    }

    /**
     * Approve all still-submitted subjects, then send one combined message per student.
     *
     * @return array{sent: int, failed: int, skipped: int, error: ?string, template: ?string, subjects: int}
     */
    public function combinedSend(User $admin, int $batchId, string $date, ?string $templateName = null): array
    {
        $date = $this->normalizeDate($date);
        $batch = Batch::query()->with('course')->findOrFail($batchId);

        $assignments = HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->whereNotNull('course_subject_id')
            ->whereDate('homework_date', $date)
            ->whereIn('status', [
                HomeworkAssignmentStatus::Approved->value,
                HomeworkAssignmentStatus::Sent->value,
            ])
            ->with('courseSubject')
            ->orderBy('course_subject_id')
            ->get();

        if ($assignments->isEmpty()) {
            return [
                'sent' => 0, 'failed' => 0, 'skipped' => 0, 'subjects' => 0,
                'template' => null,
                'error' => 'Approve at least one subject before sending. Submitted subjects must be approved first.',
            ];
        }

        foreach ($assignments as $assignment) {
            $assignment->ensurePublicToken();

            if (blank($assignment->published_at)) {
                $assignment->forceFill(['published_at' => now()])->save();
            }
        }

        $dateLabel = $batch->displayLabel().' · '.Carbon::parse($date)->format('d M Y');

        $result = $this->whatsapp->notifyCombined($batch, $dateLabel, $assignments, $templateName);

        if ($result['sent'] > 0) {
            HomeworkAssignment::query()
                ->whereIn('id', $assignments->pluck('id'))
                ->update([
                    'status' => HomeworkAssignmentStatus::Sent->value,
                    'combined_sent_at' => now(),
                    'whatsapp_sent_count' => $result['sent'],
                    'whatsapp_failed_count' => $result['failed'],
                ]);
        }

        return [...$result, 'subjects' => $assignments->count()];
    }
}
