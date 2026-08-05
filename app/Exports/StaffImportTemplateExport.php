<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StaffImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return [
            'Staff ID',
            'Name',
            'Mobile',
            'Designation',
            'Email',
        ];
    }

    public function array(): array
    {
        return [
            ['STF001', 'Anita Sharma', '9876543210', 'Teacher', ''],
            ['STF002', 'Ravi Kumar', '9123456780', 'Counsellor', ''],
        ];
    }
}
