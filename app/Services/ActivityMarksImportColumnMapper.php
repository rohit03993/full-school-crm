<?php

namespace App\Services;

use App\Support\ActivityMarksImportFields;

class ActivityMarksImportColumnMapper
{
    /**
     * @param  list<string|null>  $headers
     * @return array{roll_column: int|null, subject_columns: list<int>}
     */
    public function guess(array $headers): array
    {
        $rollColumn = null;
        $subjectColumns = [];

        foreach ($headers as $index => $header) {
            if ($header === null) {
                continue;
            }

            $normalized = $this->normalizeHeader($header);

            if ($rollColumn === null && $this->looksLikeRollColumn($normalized)) {
                $rollColumn = $index;

                continue;
            }

            if ($this->looksLikeNonSubjectColumn($normalized)) {
                continue;
            }

            $subjectColumns[] = $index;
        }

        if ($rollColumn !== null) {
            $subjectColumns = array_values(array_filter(
                $subjectColumns,
                fn (int $index): bool => $index !== $rollColumn,
            ));
        }

        return [
            'roll_column' => $rollColumn,
            'subject_columns' => $subjectColumns,
        ];
    }

    /**
     * @param  array{roll_column: int|null, subject_columns: list<int>}  $mapping
     * @return list<string>
     */
    public function missingRequiredFields(array $mapping): array
    {
        $missing = [];

        if ($mapping['roll_column'] === null) {
            $missing[] = ActivityMarksImportFields::labels()[ActivityMarksImportFields::ROLL_NUMBER];
        }

        if ($mapping['subject_columns'] === []) {
            $missing[] = 'At least one subject column';
        }

        return $missing;
    }

    protected function normalizeHeader(string $header): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', ' ', trim($header)) ?? trim($header));
    }

    protected function looksLikeRollColumn(string $normalized): bool
    {
        return str_contains($normalized, 'roll')
            || str_contains($normalized, 'enrollment')
            || $normalized === 'sr no'
            || $normalized === 's no';
    }

    protected function looksLikeNonSubjectColumn(string $normalized): bool
    {
        foreach ([
            'student name',
            'name',
            'father',
            'mobile',
            'phone',
            'class',
            'section',
            'batch',
            'total',
            'mark obtain',
            'marks obtain',
            'obtained',
            'percentage',
            'percent',
            'percentile',
            'rank',
            'remarks',
            'remark',
            'right ans',
            'wrong ans',
            's no',
            's.no',
            'sr no',
            'serial',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
