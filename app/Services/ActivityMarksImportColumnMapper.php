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
        $rollColumn = $this->guessRollColumn($headers);
        $subjectColumns = [];

        foreach ($headers as $index => $header) {
            if ($header === null) {
                continue;
            }

            if ($rollColumn !== null && (int) $index === $rollColumn) {
                continue;
            }

            $normalized = $this->normalizeHeader($header);

            if ($this->looksLikeNonSubjectColumn($normalized)) {
                continue;
            }

            $subjectColumns[] = (int) $index;
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

        if ($mapping['roll_column'] === null || $mapping['roll_column'] === '') {
            $missing[] = ActivityMarksImportFields::labels()[ActivityMarksImportFields::ROLL_NUMBER];
        }

        if ($mapping['subject_columns'] === []) {
            $missing[] = 'At least one subject column';
        }

        return $missing;
    }

    /**
     * @param  list<string|null>  $headers
     */
    public function guessRollColumn(array $headers): ?int
    {
        $ranked = [];

        foreach ($headers as $index => $header) {
            if ($header === null) {
                continue;
            }

            $score = $this->rollColumnScore($this->normalizeHeader($header));

            if ($score > 0) {
                $ranked[(int) $index] = $score;
            }
        }

        if ($ranked === []) {
            return null;
        }

        arsort($ranked);

        return (int) array_key_first($ranked);
    }

    protected function normalizeHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', trim($header)) ?? trim($header)));
    }

    protected function rollColumnScore(string $normalized): int
    {
        $compact = str_replace(' ', '', $normalized);

        if (str_contains($normalized, 'payroll') || $normalized === 'role') {
            return 0;
        }

        if (str_contains($normalized, 'roll') || $compact === 'rno' || $normalized === 'r no') {
            return 100;
        }

        if (str_contains($normalized, 'enrollment') || str_contains($normalized, 'enrolment')) {
            return 90;
        }

        if (str_contains($normalized, 'enroll') || str_contains($normalized, 'enrol')) {
            return 80;
        }

        if (str_contains($normalized, 'admission') || $compact === 'admno' || $normalized === 'adm no') {
            return 70;
        }

        if (str_contains($normalized, 'scholar')) {
            return 60;
        }

        if ($compact === 'regno' || $normalized === 'reg no' || $normalized === 'regd no') {
            return 50;
        }

        return 0;
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
            'sr no',
            'serial',
            'roll',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return $normalized === 'r no' || str_replace(' ', '', $normalized) === 'rno';
    }
}
