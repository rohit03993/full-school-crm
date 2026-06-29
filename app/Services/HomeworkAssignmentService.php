<?php

namespace App\Services;

use App\Enums\HomeworkContentType;
use App\Models\BatchStudent;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkView;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class HomeworkAssignmentService
{
    /**
     * @param  array{
     *     batch_id: int,
     *     title: string,
     *     description: string,
     *     file_path?: string|null,
     *     send_whatsapp?: bool,
     *     whatsapp_template_name?: string|null
     * }  $data
     */
    public function create(User $creator, array $data): HomeworkAssignment
    {
        $contentType = HomeworkContentType::Text;
        $filePath = filled($data['file_path'] ?? null) ? (string) $data['file_path'] : null;

        if ($filePath !== null) {
            $mime = (string) Storage::disk('public')->mimeType($filePath);
            $contentType = str_starts_with($mime, 'image/')
                ? HomeworkContentType::Image
                : HomeworkContentType::Pdf;
        }

        $assignment = HomeworkAssignment::query()->create([
            'batch_id' => (int) $data['batch_id'],
            'created_by_user_id' => $creator->id,
            'title' => trim((string) $data['title']),
            'description' => trim((string) $data['description']),
            'content_type' => $contentType,
            'file_path' => $filePath,
            'published_at' => now(),
        ]);

        if ((bool) ($data['send_whatsapp'] ?? false)) {
            $result = app(HomeworkWhatsAppService::class)->notifyBatch(
                $assignment,
                filled($data['whatsapp_template_name'] ?? null) ? (string) $data['whatsapp_template_name'] : null,
            );

            $assignment->update([
                'whatsapp_sent_count' => $result['sent'],
                'whatsapp_failed_count' => $result['failed'],
            ]);
        }

        return $assignment->fresh(['batch']);
    }

    public function recordView(HomeworkAssignment $assignment, Student $student): HomeworkView
    {
        return HomeworkView::query()->updateOrCreate(
            [
                'homework_assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ],
            ['viewed_at' => now()],
        );
    }

    public function studentCanAccess(HomeworkAssignment $assignment, Student $student): bool
    {
        return BatchStudent::query()
            ->where('batch_id', $assignment->batch_id)
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return Collection<int, HomeworkAssignment>
     */
    public function assignmentsForStudent(Student $student): Collection
    {
        $batchIds = BatchStudent::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->pluck('batch_id');

        if ($batchIds->isEmpty()) {
            return collect();
        }

        return HomeworkAssignment::query()
            ->with(['batch', 'views' => fn ($query) => $query->where('student_id', $student->id)])
            ->whereIn('batch_id', $batchIds)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get();
    }

    /**
     * @return Collection<int, Student>
     */
    public function batchStudentsWithMobile(int $batchId): Collection
    {
        return Student::query()
            ->whereHas('batchStudents', function ($query) use ($batchId): void {
                $query->where('batch_id', $batchId)->where('is_active', true);
            })
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->with('activeEnrollment')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array{
     *     student: Student,
     *     viewed: bool,
     *     viewed_at: ?string
     * }>
     */
    public function viewReport(HomeworkAssignment $assignment): array
    {
        $students = $this->batchStudentsWithMobile($assignment->batch_id);
        $views = $assignment->views()
            ->get(['student_id', 'viewed_at'])
            ->keyBy('student_id');

        return $students->map(function (Student $student) use ($views): array {
            $view = $views->get($student->id);

            return [
                'student' => $student,
                'viewed' => $view !== null,
                'viewed_at' => $view?->viewed_at?->format('d M Y, h:i A'),
            ];
        })->values()->all();
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     student: Student,
     *     viewed: bool,
     *     viewed_at: ?string
     * }>
     */
    public function paginatedViewReport(
        HomeworkAssignment $assignment,
        ?int $page = null,
        ?int $perPage = null,
    ): LengthAwarePaginator {
        $perPage = $perPage ?? CrmPagination::PER_PAGE;
        $page = $page ?? LengthAwarePaginator::resolveCurrentPage('tracking_page');
        $items = collect($this->viewReport($assignment));

        return new LengthAwarePaginator(
            $items->slice(($page - 1) * $perPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'tracking_page',
            ],
        );
    }

    /**
     * @return Collection<int, HomeworkAssignment>
     */
    public function assignmentsForStudentProfile(Student $student): Collection
    {
        return $this->assignmentsForStudent($student)->take(CrmPagination::PER_PAGE);
    }

    public function unviewedCountForStudent(Student $student): int
    {
        return $this->assignmentsForStudent($student)
            ->filter(fn (HomeworkAssignment $assignment): bool => $assignment->views->isEmpty())
            ->count();
    }
}
