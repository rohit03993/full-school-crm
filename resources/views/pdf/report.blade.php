<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 20px; }
        h1 { font-size: 14px; margin: 0 0 4px; color: #b45309; }
        .meta { font-size: 9px; color: #6b7280; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #fffbeb; color: #92400e; text-align: left; padding: 6px; border: 1px solid #e5e7eb; }
        td { padding: 5px 6px; border: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <p class="meta">
        {{ $institute['name'] }}
        @if (! empty($institute['receipt_header'])) · {{ $institute['receipt_header'] }} @endif
        · Generated {{ $report['generated_at'] }}
    </p>

    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) }}">No records for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
