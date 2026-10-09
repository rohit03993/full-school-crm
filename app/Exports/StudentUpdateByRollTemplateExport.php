<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentUpdateByRollTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return [
            'Roll Number',
            'Address',
            'City',
            'State',
            'Pincode',
            'Student Name',
            'Father Name',
            'Mobile',
            'Alternate Mobile',
            'Email',
        ];
    }

    public function array(): array
    {
        return [
            ['101', '12 Station Road', 'Agra', 'Uttar Pradesh', '282001', '', '', '', '', ''],
        ];
    }
}
