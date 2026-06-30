<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseInstallmentTemplate;
use Illuminate\Validation\ValidationException;

class CourseInstallmentTemplateService
{
    /**
     * @param  array<int, array{label?: string, percentage?: mixed, due_days_after_enrollment?: mixed}>  $rows
     */
    public function sync(Course $course, array $rows): void
    {
        $normalized = $this->normalize($rows);

        $course->installmentTemplates()->delete();

        foreach ($normalized as $row) {
            CourseInstallmentTemplate::query()->create([
                'course_id' => $course->id,
                ...$row,
            ]);
        }
    }

    /**
     * @param  array<int, array{label?: string, percentage?: mixed, due_days_after_enrollment?: mixed}>  $rows
     * @return array<int, array{label: string, percentage: float, due_days_after_enrollment: int, sort_order: int}>
     */
    public function normalize(array $rows): array
    {
        $normalized = [];

        foreach (array_values($rows) as $index => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $percentage = round((float) ($row['percentage'] ?? 0), 2);
            $dueDays = max(0, (int) ($row['due_days_after_enrollment'] ?? 0));

            if ($label === '' && $percentage <= 0) {
                continue;
            }

            if ($label === '') {
                throw ValidationException::withMessages([
                    'installment_templates' => 'Each installment template needs a label.',
                ]);
            }

            if ($percentage <= 0) {
                throw ValidationException::withMessages([
                    'installment_templates' => "Percentage for “{$label}” must be greater than zero.",
                ]);
            }

            $normalized[] = [
                'label' => $label,
                'percentage' => $percentage,
                'due_days_after_enrollment' => $dueDays,
                'sort_order' => $index + 1,
            ];
        }

        $total = round((float) collect($normalized)->sum('percentage'), 2);

        if ($normalized !== [] && abs($total - 100) > 0.5) {
            throw ValidationException::withMessages([
                'installment_templates' => 'Installment percentages should add up to 100% (currently '.$total.'%).',
            ]);
        }

        return $normalized;
    }
}
