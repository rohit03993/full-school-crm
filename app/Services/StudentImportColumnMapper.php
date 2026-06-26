<?php

namespace App\Services;

use App\Support\StudentImportFields;

class StudentImportColumnMapper
{
    /**
     * @var array<string, list<string>>
     */
    protected array $aliases = [
        StudentImportFields::ROLL_NUMBER => [
            'roll', 'roll no', 'roll number', 'rollno', 'roll_no', 'enrollment number',
            'enrollment no', 'enrolment number', 'student roll', 'sr no', 'sr. no',
        ],
        StudentImportFields::NAME => [
            'name', 'student name', 'student', 'full name', 'candidate name',
        ],
        StudentImportFields::FATHER_NAME => [
            'father', 'father name', 'fathers name', "father's name", 'parent name',
            'guardian name', 's/o', 'parent',
        ],
        StudentImportFields::MOBILE => [
            'mobile', 'phone', 'contact', 'primary mobile', 'primary whatsapp', 'secondary whatsapp',
            'mobile number', 'phone number', 'contact number', 'whatsapp', 'cell',
        ],
        StudentImportFields::DATE_OF_BIRTH => [
            'dob', 'date of birth', 'birth date', 'birthdate', 'date_of_birth',
        ],
        StudentImportFields::GENDER => [
            'gender', 'sex',
        ],
        StudentImportFields::BATCH_SECTION => [
            'batch', 'section', 'batch section', 'class section', 'class course',
            'class (course)', 'batch name', 'class batch', 'programme batch', 'division', 'group',
        ],
    ];

    /**
     * @param  list<string|null>  $headers
     * @return array<int, string> column index => field key
     */
    public function guess(array $headers): array
    {
        $mapping = [];
        $usedFields = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);

            if ($normalized === '') {
                $mapping[$index] = StudentImportFields::SKIP;

                continue;
            }

            $field = $this->matchField($normalized, $usedFields);
            $mapping[$index] = $field;

            if ($field !== StudentImportFields::SKIP) {
                $usedFields[] = $field;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<int, string>  $columnMapping
     * @return list<string>
     */
    public function missingRequiredFields(array $columnMapping): array
    {
        $mapped = array_values(array_filter(
            $columnMapping,
            fn (string $field): bool => $field !== StudentImportFields::SKIP,
        ));

        return array_values(array_diff(StudentImportFields::required(), $mapped));
    }

    protected function normalizeHeader(?string $header): string
    {
        $header = strtolower(trim((string) $header));
        $header = preg_replace('/[^a-z0-9\s\/_-]+/', '', $header) ?? '';
        $header = preg_replace('/\s+/', ' ', $header) ?? '';

        return trim($header);
    }

    /**
     * @param  list<string>  $usedFields
     */
    protected function matchField(string $normalizedHeader, array $usedFields): string
    {
        foreach ($this->aliases as $field => $aliases) {
            if (in_array($field, $usedFields, true)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if ($normalizedHeader === $alias || str_contains($normalizedHeader, $alias)) {
                    return $field;
                }
            }
        }

        return StudentImportFields::SKIP;
    }
}
