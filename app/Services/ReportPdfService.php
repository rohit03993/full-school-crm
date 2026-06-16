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
}
