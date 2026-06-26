<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use RuntimeException;

class StudentImportFileReader
{
    public const MAX_ROWS = 2000;

    /**
     * @return array{
     *     headers: list<string|null>,
     *     rows: list<list<string|null>>,
     *     path: string
     * }
     */
    public function storeAndParse(UploadedFile $file, bool $detectMarksHeaderRow = false): array
    {
        $path = $file->store('temp-student-imports');

        if (! $path) {
            throw new RuntimeException('Could not store the uploaded file.');
        }

        $absolutePath = Storage::disk('local')->path($path);

        if (! is_readable($absolutePath)) {
            throw new RuntimeException('Uploaded file could not be read after storage.');
        }

        $parsed = $this->parse($absolutePath, $detectMarksHeaderRow);

        return [
            ...$parsed,
            'path' => $path,
        ];
    }

    /**
     * @return array{headers: list<string|null>, rows: list<list<string|null>>}
     */
    public function parse(string $absolutePath, bool $detectMarksHeaderRow = false): array
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseDelimited($absolutePath);
        }

        return $this->parseSpreadsheet($absolutePath, $detectMarksHeaderRow);
    }

    public function deleteStoredFile(?string $relativePath): void
    {
        if (filled($relativePath)) {
            Storage::disk('local')->delete($relativePath);
        }
    }

    /**
     * @return array{headers: list<string|null>, rows: list<list<string|null>>}
     */
    protected function parseSpreadsheet(string $absolutePath, bool $detectMarksHeaderRow = false): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($absolutePath)->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        if ($highestRow < 1 || $highestColumnIndex < 1) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        $headerRowIndex = $detectMarksHeaderRow
            ? $this->detectMarksHeaderRowIndex($sheet, $highestRow, $highestColumnIndex)
            : 1;

        $headers = [];
        $rows = [];

        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            $rowData = [];

            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $rowData[] = $this->cellValueFromSpreadsheet(
                    $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex).$rowIndex),
                );
            }

            if ($rowIndex === $headerRowIndex) {
                $headers = array_map(
                    fn ($value): ?string => filled($value) ? trim((string) $value) : null,
                    $rowData,
                );

                continue;
            }

            if ($rowIndex < $headerRowIndex) {
                continue;
            }

            if ($this->rowIsEmpty($rowData)) {
                continue;
            }

            $rows[] = $rowData;

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        if ($rows === []) {
            throw new RuntimeException('No student rows were found below the header row.');
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    protected function detectMarksHeaderRowIndex(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $highestRow,
        int $highestColumnIndex,
    ): int {
        $mapper = app(ActivityMarksImportColumnMapper::class);

        for ($rowIndex = 1; $rowIndex <= min(10, $highestRow); $rowIndex++) {
            $headers = [];

            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $value = $this->cellValueFromSpreadsheet(
                    $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex).$rowIndex),
                );
                $headers[] = filled($value) ? trim((string) $value) : null;
            }

            $mapping = $mapper->guess($headers);

            if ($mapping['roll_column'] !== null && $mapping['subject_columns'] !== []) {
                return $rowIndex;
            }
        }

        return 1;
    }

    /**
     * @return array{headers: list<string|null>, rows: list<list<string|null>>}
     */
    protected function parseDelimited(string $absolutePath): array
    {
        $sheets = Excel::toArray(null, $absolutePath);
        $sheet = $sheets[0] ?? [];

        if ($sheet === []) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        $headers = array_map(
            fn ($value): ?string => filled($value) ? trim((string) $value) : null,
            array_values($sheet[0] ?? []),
        );

        $rows = [];

        foreach (array_slice($sheet, 1) as $row) {
            $row = array_values($row);

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $rows[] = array_map(
                fn ($value): ?string => $this->cellToString($value),
                $row,
            );

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        if ($rows === []) {
            throw new RuntimeException('No student rows were found below the header row.');
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    protected function cellValueFromSpreadsheet(Cell $cell): ?string
    {
        $value = $cell->getCalculatedValue();

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        return $this->cellToString($value);
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (filled($value)) {
                return false;
            }
        }

        return true;
    }

    protected function cellToString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $this->numericToDigitString($value);
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        if (preg_match('/^[\d.]+[eE][+\-]?\d+$/', $string)) {
            if (\App\Support\IndianMobileNumber::isLossyScientificNotation($string)) {
                return $string;
            }

            return $this->numericToDigitString((float) $string);
        }

        return $string;
    }

    protected function numericToDigitString(float $value): string
    {
        if (abs($value) >= 1_000_000_000 && abs($value - round($value)) < 0.0001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
