<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
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
    public function storeAndParse(UploadedFile $file): array
    {
        $path = $file->store('temp-student-imports');

        if (! $path) {
            throw new RuntimeException('Could not store the uploaded file.');
        }

        $absolutePath = Storage::path($path);

        if (! is_readable($absolutePath)) {
            throw new RuntimeException('Uploaded file could not be read after storage.');
        }

        $parsed = $this->parse($absolutePath);

        return [
            ...$parsed,
            'path' => $path,
        ];
    }

    /**
     * @return array{headers: list<string|null>, rows: list<list<string|null>>}
     */
    public function parse(string $absolutePath): array
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
                fn ($value): ?string => filled($value) ? trim((string) $value) : null,
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

    public function deleteStoredFile(?string $relativePath): void
    {
        if (filled($relativePath)) {
            Storage::delete($relativePath);
        }
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
}
