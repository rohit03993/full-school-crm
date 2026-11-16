<?php

namespace App\Services;

use App\Support\InstituteSettings;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdfService
{
    /**
     * @param  array{
     *     title: string,
     *     columns: array<int, string>,
     *     rows: array<int, array<int, string|int|float|null>>,
     *     generated_at: string
     * }  $report
     */
    public function generate(array $report): string
    {
        return Pdf::loadView('pdf.report', [
            'report' => $report,
            'institute' => InstituteSettings::forDocuments(),
        ])->setPaper('a4', 'landscape')->output();
    }

    /**
     * @param  array{
     *     title: string,
     *     generated_at: string,
     *     dates: list<array{date: string, label: string, weekday: string}>,
     *     sheets: list<array<string, mixed>>
     * }  $register
     */
    public function register(array $register): string
    {
        return Pdf::loadView('pdf.attendance-register', [
            'register' => $register,
            'institute' => InstituteSettings::forDocuments(),
        ])->setPaper('a4', 'landscape')->output();
    }
}
