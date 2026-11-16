<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $register['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1f2937; margin: 16px; }
        h1 { font-size: 13px; margin: 0 0 2px; color: #b45309; }
        h2 { font-size: 11px; margin: 0 0 8px; }
        .meta { font-size: 8px; color: #6b7280; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th { background: #fffbeb; color: #92400e; text-align: center; padding: 3px 2px; border: 1px solid #e5e7eb; }
        td { padding: 3px 2px; border: 1px solid #e5e7eb; text-align: center; }
        td.name { text-align: left; white-space: nowrap; }
        th.sun, td.sun { background: #fef3c7; }
        td.p { color: #047857; font-weight: bold; }
        td.a { color: #b91c1c; font-weight: bold; }
        td.l { color: #b45309; font-weight: bold; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
    </style>
</head>
<body>
    @forelse ($register['sheets'] as $sheet)
        @php
            $chunks = array_chunk($register['dates'], 15);
            $lastChunk = count($chunks) - 1;
        @endphp
        @foreach ($chunks as $chunkIndex => $dates)
            <div class="page">
                <h1>{{ $register['title'] }}</h1>
                <p class="meta">{{ $institute['name'] }} · Generated {{ $register['generated_at'] }} · P present, A absent, L leave. A blank day is before the student joined.</p>
                <h2>{{ $sheet['batch_name'] }}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Roll</th>
                            <th>Student</th>
                            @foreach ($dates as $date)
                                <th @class(['sun' => $date['weekday'] === 'Sun'])>{{ $date['label'] }}<br>{{ $date['weekday'] }}</th>
                            @endforeach
                            @if ($chunkIndex === $lastChunk)
                                <th>Present</th>
                                <th>Leave</th>
                                <th>Absent</th>
                                <th>Days</th>
                                <th>%</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sheet['students'] as $student)
                            <tr>
                                <td class="name">{{ $student['roll'] }}</td>
                                <td class="name">{{ $student['name'] }}</td>
                                @foreach ($dates as $offset => $date)
                                    @php
                                        $mark = $student['marks'][($chunkIndex * 15) + $offset] ?? '';
                                    @endphp
                                    <td @class([
                                        'sun' => $date['weekday'] === 'Sun',
                                        'p' => $mark === 'P',
                                        'a' => $mark === 'A',
                                        'l' => $mark === 'L',
                                    ])>{{ $mark }}</td>
                                @endforeach
                                @if ($chunkIndex === $lastChunk)
                                    <td>{{ $student['present'] }}</td>
                                    <td>{{ $student['leave'] }}</td>
                                    <td>{{ $student['absent'] }}</td>
                                    <td>{{ $student['days'] }}</td>
                                    <td>{{ $student['percentage'] }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + count($dates) }}">No students for this batch.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    @empty
        <h1>{{ $register['title'] }}</h1>
        <p>No students match these filters.</p>
    @endforelse
</body>
</html>
