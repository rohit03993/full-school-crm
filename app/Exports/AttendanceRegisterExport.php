<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceRegisterExport implements WithMultipleSheets
{
    /**
     * @param  array{
     *     title: string,
     *     generated_at: string,
     *     dates: list<array{date: string, label: string, weekday: string}>,
     *     sheets: list<array{batch_id: int, batch_name: string, students: list<array<string, mixed>>}>
     * }  $register
     */
    public function __construct(
        protected array $register,
    ) {}

    /**
     * @return list<AttendanceRegisterSheet>
     */
    public function sheets(): array
    {
        $sheets = $this->register['sheets'];

        if ($sheets === []) {
            $sheets = [[
                'batch_id' => 0,
                'batch_name' => 'Attendance',
                'students' => [],
            ]];
        }

        $usedTitles = [];

        return array_map(function (array $sheet) use (&$usedTitles): AttendanceRegisterSheet {
            $title = $this->sheetTitle((string) $sheet['batch_name'], (int) $sheet['batch_id'], $usedTitles);
            $usedTitles[$title] = true;

            return new AttendanceRegisterSheet($this->register, $sheet, $title);
        }, $sheets);
    }

    /**
     * @param  array<string, bool>  $usedTitles
     */
    protected function sheetTitle(string $name, int $id, array $usedTitles): string
    {
        $clean = trim((string) preg_replace('/[:\\\\\\/\\?\\*\\[\\]]+/', ' ', $name));
        $title = mb_substr($clean !== '' ? $clean : 'Batch '.$id, 0, 31);

        if (! isset($usedTitles[$title])) {
            return $title;
        }

        $suffix = ' '.$id;

        return mb_substr($title, 0, 31 - mb_strlen($suffix)).$suffix;
    }
}
