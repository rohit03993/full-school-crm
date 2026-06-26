<?php

namespace Tests\Unit;

use App\Services\StudentImportFileReader;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentImportFileReaderTest extends TestCase
{
    public function test_reads_twelve_digit_mobile_from_xlsx_with_full_precision(): void
    {
        $path = $this->createTempSpreadsheet([
            ['Roll', 'Name', 'Primary WhatsApp'],
            ['1', 'Rohit', 919000009000],
            ['2', 'Mayra', 919027620525],
            ['3', 'Ten Digit', 8410054825],
        ]);

        $parsed = app(StudentImportFileReader::class)->parse($path);

        $this->assertSame('919000009000', $parsed['rows'][0][2]);
        $this->assertSame('919027620525', $parsed['rows'][1][2]);
        $this->assertSame('8410054825', $parsed['rows'][2][2]);

        @unlink($path);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    protected function createTempSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $coordinate = chr(ord('A') + $columnIndex).($rowIndex + 1);

                if (is_int($value) && $value >= 1_000_000_000) {
                    $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValue($coordinate, $value);
                }
            }
        }

        $path = storage_path('app/test-student-import-'.uniqid().'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
