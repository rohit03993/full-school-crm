<?php

namespace App\Services;

use App\Enums\StandardCoursePlanStatus;
use App\Models\CourseSubject;
use App\Models\StandardCoursePlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StandardCoursePlanService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function assertCanSave(array $data, ?int $ignorePlanId = null): void
    {
        $courseId = (int) ($data['course_id'] ?? 0);
        $subjectId = (int) ($data['course_subject_id'] ?? 0);
        $sessionId = (int) ($data['academic_session_id'] ?? 0);

        $subjectBelongsToCourse = CourseSubject::query()
            ->whereKey($subjectId)
            ->where('course_id', $courseId)
            ->exists();

        if (! $subjectBelongsToCourse) {
            throw ValidationException::withMessages([
                'course_subject_id' => 'Choose a subject that belongs to this programme.',
            ]);
        }

        $alreadyExists = StandardCoursePlan::query()
            ->where('academic_session_id', $sessionId)
            ->where('course_id', $courseId)
            ->where('course_subject_id', $subjectId)
            ->when($ignorePlanId, fn ($query) => $query->whereKeyNot($ignorePlanId))
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'course_subject_id' => 'This programme and subject already have a course plan for that year.',
            ]);
        }
    }

    public function contentIsReady(StandardCoursePlan $plan): bool
    {
        $plan->load(['chapters.topics']);

        foreach ($plan->chapters as $chapter) {
            if (blank($chapter->name)) {
                continue;
            }

            foreach ($chapter->topics as $topic) {
                if (filled($topic->name) && (int) $topic->planned_minutes > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function markReady(StandardCoursePlan $plan, User $user): void
    {
        if (! $this->contentIsReady($plan)) {
            throw ValidationException::withMessages([
                'chapters' => 'Add at least one chapter, one topic, and planned minutes above zero before marking the plan ready.',
            ]);
        }

        $plan->update([
            'status' => StandardCoursePlanStatus::Ready,
            'ready_at' => now(),
            'ready_by_user_id' => $user->id,
        ]);
    }

    public function markDraft(StandardCoursePlan $plan): void
    {
        $plan->update([
            'status' => StandardCoursePlanStatus::Draft,
            'ready_at' => null,
            'ready_by_user_id' => null,
        ]);
    }

    public function keepReadyOnlyWhenContentAllows(StandardCoursePlan $plan): void
    {
        if ($plan->status !== StandardCoursePlanStatus::Ready) {
            return;
        }

        if (! $this->contentIsReady($plan)) {
            $this->markDraft($plan);
        }
    }
}
