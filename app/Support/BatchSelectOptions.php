<?php

namespace App\Support;

use App\Enums\BatchStatus;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Builder;

final class BatchSelectOptions
{
    /**
     * @return array<int, string>
     */
    public static function forSession(?int $sessionId, bool $activeOnly = true): array
    {
        if (! $sessionId) {
            return [];
        }

        $query = self::baseQuery($activeOnly)
            ->where('academic_session_id', $sessionId)
            ->orderBy('name');

        return ClassSectionLabel::options($query->get());
    }

    /**
     * @return array<int, string>
     */
    public static function forCourse(?int $courseId, ?int $sessionId = null, bool $activeOnly = true): array
    {
        if (! $courseId) {
            return [];
        }

        $query = self::baseQuery($activeOnly)
            ->where('course_id', $courseId)
            ->orderBy('name');

        if ($sessionId) {
            $query->where('academic_session_id', $sessionId);
        }

        return ClassSectionLabel::options($query->get());
    }

    /**
     * @return Builder<Batch>
     */
    protected static function baseQuery(bool $activeOnly): Builder
    {
        $query = Batch::query()->with(['course', 'academicSession']);

        if ($activeOnly) {
            $query->where('status', BatchStatus::Active);
        }

        return $query;
    }
}
