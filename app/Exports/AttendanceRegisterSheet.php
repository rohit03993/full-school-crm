<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceRegisterSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array{dates: list<array{date: string, label: string, weekday: string}>}  $register
     * @param  array{batch_name: string, students: list<array<string, mixed>>}  $sheet
     */
    public function __construct(
        protected array $register,
        protected array $sheet,
        protected string $sheetTitle,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /**
     * @return list<array<int, string|int|float|null>>
     */
    public function array(): array
    {
        $dateLabels = ['Roll', 'Student'];
        $weekdays = ['', ''];

        foreach ($this->register['dates'] as $date) {
            $dateLabels[] = $date['label'];
            $weekdays[] = $date['weekday'];
        }

        foreach (['Present', 'Leave', 'Absent', 'Days', '%'] as $total) {
            $dateLabels[] = $total;
            $weekdays[] = '';
        }

        $rows = [$dateLabels, $weekdays];

        foreach ($this->sheet['students'] as $student) {
            $rows[] = [
                $student['roll'],
                $student['name'],
                ...$student['marks'],
                $student['present'],
                $student['leave'],
                $student['absent'],
                $student['days'],
                $student['percentage'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, array<string, bool>>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
        ];
    }
}
