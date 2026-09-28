<?php

namespace App\Services;

use App\Enums\BatchStaffRole;
use App\Enums\CrmPermission;
use App\Enums\SectionCoursePlanStatus;
use App\Enums\StandardCoursePlanStatus;
use App\Support\CrmAccess;
use App\Models\Batch;
use App\Models\SectionCoursePlan;
use App\Models\StandardCoursePlan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SectionCoursePlanService
{
    public function copyFromStandard(StandardCoursePlan $plan, Batch $batch, User $user): SectionCoursePlan
    {
        $plan->loadMissing(['chapters.topics', 'practicals']);

        if ($plan->status !== StandardCoursePlanStatus::Ready) {
            throw ValidationException::withMessages([
                'standard_course_plan_id' => 'Mark the course plan ready before copying it onto a section.',
            ]);
        }

        if ((int) $batch->course_id !== (int) $plan->course_id
            || (int) $batch->academic_session_id !== (int) $plan->academic_session_id) {
            throw ValidationException::withMessages([
                'batch_id' => 'Choose a section from the same year and programme.',
            ]);
        }

        $sectionStudiesSubject = $batch->subjects()
            ->whereKey($plan->course_subject_id)
            ->exists();

        if (! $sectionStudiesSubject) {
            throw ValidationException::withMessages([
                'batch_id' => 'This section does not study that subject.',
            ]);
        }

        $alreadyCopied = SectionCoursePlan::query()
            ->where('batch_id', $batch->id)
            ->where('course_subject_id', $plan->course_subject_id)
            ->exists();

        if ($alreadyCopied) {
            throw ValidationException::withMessages([
                'batch_id' => 'This section already has a plan for that subject.',
            ]);
        }

        return DB::transaction(function () use ($plan, $batch, $user): SectionCoursePlan {
            $sectionPlan = SectionCoursePlan::query()->create([
                'standard_course_plan_id' => $plan->id,
                'batch_id' => $batch->id,
                'academic_session_id' => $plan->academic_session_id,
                'course_id' => $plan->course_id,
                'course_subject_id' => $plan->course_subject_id,
                'lecture_minutes' => max(1, (int) $plan->lecture_minutes),
                'status' => SectionCoursePlanStatus::Draft,
                'version' => 0,
                'copied_by_user_id' => $user->id,
            ]);

            foreach ($plan->chapters as $chapter) {
                $copiedChapter = $sectionPlan->chapters()->create([
                    'name' => $chapter->name,
                    'estimated_marks' => $chapter->estimated_marks,
                    'sort_order' => $chapter->sort_order,
                ]);

                foreach ($chapter->topics as $topic) {
                    $copiedChapter->topics()->create([
                        'name' => $topic->name,
                        'planned_minutes' => $topic->planned_minutes,
                        'reference_book' => $topic->reference_book,
                        'dpp_count' => $topic->dpp_count,
                        'quiz_count' => $topic->quiz_count,
                        'test_count' => $topic->test_count,
                        'sort_order' => $topic->sort_order,
                    ]);
                }
            }

            foreach ($plan->practicals as $practical) {
                $sectionPlan->practicals()->create([
                    'name' => $practical->name,
                    'kind' => $practical->kind,
                    'planned_minutes' => $practical->planned_minutes,
                    'estimated_marks' => $practical->estimated_marks,
                    'sort_order' => $practical->sort_order,
                ]);
            }

            return $sectionPlan->fresh(['chapters.topics', 'practicals']);
        });
    }

    public function canEditContent(SectionCoursePlan $plan, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! in_array($plan->status, [SectionCoursePlanStatus::Draft, SectionCoursePlanStatus::SentBack], true)) {
            return false;
        }

        return $this->isSubjectTeacher($plan, $user);
    }

    public function submit(SectionCoursePlan $plan, User $user): void
    {
        if (! $this->canEditContent($plan, $user)) {
            throw ValidationException::withMessages([
                'chapters' => 'Only the subject teacher of this section can submit, and only while the plan is still open.',
            ]);
        }

        if (! $this->contentIsReady($plan)) {
            throw ValidationException::withMessages([
                'chapters' => 'Add at least one chapter, one topic, and planned time above zero before submitting.',
            ]);
        }

        $plan->update([
            'status' => SectionCoursePlanStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->id,
            'review_comment' => null,
        ]);
    }

    public function sendBack(SectionCoursePlan $plan, User $user, string $comment): void
    {
        $this->assertCanReview($plan, $user);

        if (blank(trim($comment))) {
            throw ValidationException::withMessages([
                'comment' => 'Write a comment so the teacher knows what to change.',
            ]);
        }

        $plan->update([
            'status' => SectionCoursePlanStatus::SentBack,
            'review_comment' => trim($comment),
        ]);
    }

    public function finalize(SectionCoursePlan $plan, User $user): void
    {
        $this->assertCanReview($plan, $user);

        $nextVersion = (int) $plan->version + 1;

        DB::transaction(function () use ($plan, $user, $nextVersion): void {
            $plan->versions()->create([
                'version' => $nextVersion,
                'lecture_minutes' => max(1, (int) $plan->lecture_minutes),
                'change_reason' => $plan->change_reason,
                'snapshot' => $plan->snapshot(),
                'submitted_by_user_id' => $plan->submitted_by_user_id,
                'finalized_by_user_id' => $user->id,
                'finalized_at' => now(),
            ]);

            $plan->update([
                'status' => SectionCoursePlanStatus::Final,
                'version' => $nextVersion,
                'finalized_at' => now(),
                'finalized_by_user_id' => $user->id,
                'review_comment' => null,
                'change_reason' => null,
            ]);
        });
    }

    public function openChange(SectionCoursePlan $plan, User $user, string $reason): void
    {
        if ($plan->status !== SectionCoursePlanStatus::Final) {
            throw ValidationException::withMessages([
                'reason' => 'Only a final plan can be opened for a change.',
            ]);
        }

        if (! $this->userCanDecide($user)) {
            throw ValidationException::withMessages([
                'reason' => 'Only the academic head can open a change.',
            ]);
        }

        if (blank(trim($reason))) {
            throw ValidationException::withMessages([
                'reason' => 'Write why this plan needs a change.',
            ]);
        }

        $plan->update([
            'status' => SectionCoursePlanStatus::Draft,
            'change_reason' => trim($reason),
            'review_comment' => trim($reason),
            'submitted_at' => null,
            'submitted_by_user_id' => null,
            'finalized_at' => null,
            'finalized_by_user_id' => null,
        ]);
    }

    public function isSubjectTeacher(SectionCoursePlan $plan, User $user): bool
    {
        return $plan->batch()
            ->whereHas('staffAssignments', function ($query) use ($plan, $user): void {
                $query->where('user_id', $user->id)
                    ->where('role', BatchStaffRole::SubjectTeacher)
                    ->where('course_subject_id', $plan->course_subject_id);
            })
            ->exists();
    }

    public function contentIsReady(SectionCoursePlan $plan): bool
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

    public function studentHasFinalTopics(Student $student): bool
    {
        $batchId = $student->activeBatchStudent?->batch_id;

        if (! $batchId) {
            return false;
        }

        return SectionCoursePlan::query()
            ->where('batch_id', $batchId)
            ->where('version', '>', 0)
            ->exists();
    }

    /**
     * Topics the student may see. Minutes and the lecture length stay out.
     *
     * @return list<array{subject: string, faculty: string, chapters: list<array{name: string, topics: list<array{name: string, dpp_count: int, quiz_count: int, test_count: int}>}>}>
     */
    public function finalTopicsForStudent(Student $student): array
    {
        $batchId = $student->activeBatchStudent?->batch_id;

        if (! $batchId) {
            return [];
        }

        $plans = SectionCoursePlan::query()
            ->where('batch_id', $batchId)
            ->where('version', '>', 0)
            ->with([
                'courseSubject',
                'batch.staffAssignments.user',
                'versions',
            ])
            ->get()
            ->sortBy(fn (SectionCoursePlan $plan): string => (string) $plan->courseSubject?->name)
            ->values();

        $groups = [];

        foreach ($plans as $plan) {
            $snapshot = $plan->versions->first()?->snapshot;

            if (! is_array($snapshot)) {
                continue;
            }

            $chapters = [];

            foreach ($snapshot['chapters'] ?? [] as $chapter) {
                if (! is_array($chapter) || blank($chapter['name'] ?? null)) {
                    continue;
                }

                $topics = [];

                foreach ($chapter['topics'] ?? [] as $topic) {
                    if (! is_array($topic) || blank($topic['name'] ?? null)) {
                        continue;
                    }

                    $topics[] = [
                        'name' => (string) $topic['name'],
                        'dpp_count' => (int) ($topic['dpp_count'] ?? 0),
                        'quiz_count' => (int) ($topic['quiz_count'] ?? 0),
                        'test_count' => (int) ($topic['test_count'] ?? 0),
                    ];
                }

                $chapters[] = [
                    'name' => (string) $chapter['name'],
                    'topics' => $topics,
                ];
            }

            $groups[] = [
                'subject' => (string) ($plan->courseSubject?->name ?? 'Subject'),
                'faculty' => $plan->subjectTeacherName(),
                'chapters' => $chapters,
            ];
        }

        return $groups;
    }

    private function assertCanReview(SectionCoursePlan $plan, User $user): void
    {
        if ($plan->status !== SectionCoursePlanStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'This plan is not waiting for a decision.',
            ]);
        }

        if (! $this->userCanDecide($user)) {
            throw ValidationException::withMessages([
                'status' => 'Only the academic head can finalize or send this plan back.',
            ]);
        }

        if ((int) $plan->submitted_by_user_id === (int) $user->id) {
            throw ValidationException::withMessages([
                'status' => 'You cannot decide a plan you submitted. Another academic head must do it.',
            ]);
        }
    }

    private function userCanDecide(?User $user): bool
    {
        return $user !== null && CrmAccess::can($user, CrmPermission::AcademicsManage);
    }
}
