<?php

namespace App\Filament\Resources\Courses\Concerns;

use App\Models\Course;
use App\Services\CourseInstallmentTemplateService;

trait SyncsCourseInstallmentTemplates
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFillForInstallmentTemplates(array $data, Course $course): array
    {
        $course->loadMissing('installmentTemplates');

        $data['installment_templates'] = $course->installmentTemplates->map(fn ($row): array => [
            'label' => $row->label,
            'percentage' => (string) $row->percentage,
            'due_days_after_enrollment' => (string) $row->due_days_after_enrollment,
        ])->values()->all();

        return $data;
    }

  /**
     * @param  array<string, mixed>  $data
     */
    protected function syncCourseInstallmentTemplates(Course $course, array $data): void
    {
        app(CourseInstallmentTemplateService::class)->sync(
            $course,
            $data['installment_templates'] ?? [],
        );
    }
}
