<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ClassSectionListService
{
    /**
     * @return LengthAwarePaginator<int, Batch>
     */
    public function paginate(
        ?int $sessionId = null,
        ?string $search = null,
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        $query = Batch::query()
            ->with([
                'course.subjects',
                'academicSession',
                'trainer',
                'staffAssignments.user',
                'staffAssignments.courseSubject',
            ])
            ->withCount('activeStudents')
            ->orderByDesc('id');

        if ($sessionId) {
            $query->where('academic_session_id', $sessionId);
        }

        if (filled($search)) {
            $term = trim($search);

            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('section', 'like', "%{$term}%")
                    ->orWhereHas('course', fn (Builder $course) => $course
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', '%'.strtoupper($term).'%'));
            });
        }

        return $query->paginate(
            $perPage ?? \App\Support\CrmPagination::PER_PAGE,
            ['*'],
            'page',
            $page,
        );
    }

    /**
     * @return array{sections: int, programmes: int, students: int}
     */
    public function stats(?int $sessionId = null): array
    {
        $batchQuery = Batch::query();

        if ($sessionId) {
            $batchQuery->where('academic_session_id', $sessionId);
        }

        $batchIds = (clone $batchQuery)->pluck('id');
        $courseIds = (clone $batchQuery)->distinct()->pluck('course_id');

        $students = $batchIds->isEmpty()
            ? 0
            : \App\Models\BatchStudent::query()
                ->whereIn('batch_id', $batchIds)
                ->where('is_active', true)
                ->count();

        return [
            'sections' => $batchIds->count(),
            'programmes' => Course::query()->whereIn('id', $courseIds)->count(),
            'students' => $students,
        ];
    }
}
