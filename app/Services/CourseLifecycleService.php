<?php

namespace App\Services;

use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseLifecycleService
{
    public function __construct(
        protected BatchService $batches,
    ) {}

    /**
     * After the last section is removed, hide the programme everywhere and delete it when safe.
     */
    public function syncAfterSectionDeleted(Course $course): void
    {
        $course->refresh();

        if ($course->batches()->exists()) {
            return;
        }

        $this->hideProgramme($course);

        if ($course->fresh()->deletionBlockReason()['can_delete']) {
            $this->deleteProgrammeRecord($course);
        }
    }

    /**
     * Delete every section, then remove the programme from CRM and the public site.
     */
    public function deleteProgrammeWithAllSections(Course $course): void
    {
        DB::transaction(function () use ($course): void {
            $batches = $course->batches()->get();

            foreach ($batches as $batch) {
                $this->batches->deleteSection($batch);
            }

            $course = Course::query()->find($course->id);

            if (! $course) {
                return;
            }

            if ($course->batches()->exists()) {
                throw ValidationException::withMessages([
                    'course' => 'Some sections could not be deleted (for example published exam results). Remove or complete those first.',
                ]);
            }

            $this->hideProgramme($course);

            $check = $course->fresh()->deletionBlockReason();

            if ($check['can_delete']) {
                $this->deleteProgrammeRecord($course);

                return;
            }

            throw ValidationException::withMessages([
                'course' => $check['reason'] ?? 'This class is linked to past enquiries or students and was hidden from the website instead of deleted.',
            ]);
        });
    }

    protected function hideProgramme(Course $course): void
    {
        $course->update([
            'show_on_website' => false,
            'status' => CourseStatus::Inactive,
        ]);
    }

    protected function deleteProgrammeRecord(Course $course): void
    {
        $course->delete();
    }
}
