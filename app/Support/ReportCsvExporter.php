<?php

namespace App\Support;

class ReportCsvExporter
{
    /**
     * @param  array{
     *     title: string,
     *     columns: array<int, string>,
     *     rows: array<int, array<int, string|int|float|null>>
     * }  $report
     */
    public static function export(array $report): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $report['columns']);

        foreach ($report['rows'] as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
