<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return [
            'Roll Number',
            'Student Name',
            'Father Name',
            'Mobile',
            'Date of Birth',
            'Gender',
            'Batch Name',
        ];
    }

    public function array(): array
    {
        return [
            ['101', 'Rohit Kumar', 'Mr Kumar', '9876543210', '2008-05-15', 'Male', '12th JEE Batch A (2026-27)'],
            ['102', 'Priya Sharma', 'Mr Sharma', '9123456780', '', '', '12th JEE Batch B (2026-27)'],
        ];
    }
}
