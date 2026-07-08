<?php

namespace App\Support;

use App\Models\Batch;

final class ClassSectionLabel
{
    public static function forBatch(Batch $batch, bool $includeSession = true, bool $includeShift = true): string
    {
        $batch->loadMissing(['course', 'academicSession']);

        $courseName = $batch->course?->name;

        if (! filled($courseName)) {
            return $batch->name;
        }

        $base = filled($batch->section)
            ? "{$courseName} · Section {$batch->section}"
            : self::combineProgrammeAndBatchName($courseName, $batch->name);

        $suffix = [];

        if ($includeSession && $batch->academicSession) {
            $suffix[] = $batch->academicSession->name;
        }

        if ($includeShift && $batch->shift) {
            $suffix[] = $batch->shift->label();
        }

        return $suffix === [] ? $base : $base.' · '.implode(' · ', $suffix);
    }

    public static function suggestBatchName(string $programmeName, string $section): string
    {
        $programmeName = trim($programmeName);
        $section = trim($section);

        if ($section === '') {
            return $programmeName;
        }

        $normalizedProgramme = mb_strtolower($programmeName);
        $normalizedSection = mb_strtolower($section);

        if (str_ends_with($normalizedProgramme, '-'.$normalizedSection)
            || str_ends_with($normalizedProgramme, ' '.$normalizedSection)
            || str_contains($normalizedProgramme, 'section '.$normalizedSection)) {
            return $programmeName;
        }

        return "{$programmeName}-{$section}";
    }

    public static function suggestCourseCode(string $programmeName): string
    {
        $slug = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim($programmeName)) ?? '');
        $slug = trim($slug, '-');

        if ($slug === '') {
            return 'PRG-'.now()->format('ymd');
        }

        return mb_strlen($slug) > 24 ? mb_substr($slug, 0, 24) : $slug;
    }

    /**
     * @param  iterable<int, Batch>  $batches
     * @return array<int, string>
     */
    public static function options(iterable $batches, bool $includeSession = true): array
    {
        $options = [];

        foreach ($batches as $batch) {
            $options[$batch->id] = self::forBatch($batch, $includeSession);
        }

        return $options;
    }

    protected static function combineProgrammeAndBatchName(string $programmeName, string $batchName): string
    {
        $batchName = trim($batchName);

        if ($batchName === '' || mb_strtolower($batchName) === mb_strtolower($programmeName)) {
            return $programmeName;
        }

        if (str_starts_with(mb_strtolower($batchName), mb_strtolower($programmeName))) {
            return $batchName;
        }

        return "{$programmeName} · {$batchName}";
    }
}
