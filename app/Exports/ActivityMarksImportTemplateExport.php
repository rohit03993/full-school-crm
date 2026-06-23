<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ActivityMarksImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return [
            'Roll Number',
            'Student Name',
            'Mathematics',
            'Physics',
            'Chemistry',
        ];
    }

    public function array(): array
    {
        return [
            ['101', 'Rohit Kumar', '42', '38', '45'],
            ['102', 'Priya Sharma', '35', '40', '39'],
        ];
    }
}
