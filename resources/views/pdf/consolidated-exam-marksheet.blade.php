<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Consolidated Report Card — {{ $student->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 14mm; }
        .header { text-align: center; border-bottom: 2px solid #d97706; padding-bottom: 10px; margin-bottom: 14px; }
        .brand { font-size: 20px; font-weight: bold; color: #b45309; }
        .tagline { font-size: 10px; color: #64748b; margin-top: 2px; }
        .title { text-align: center; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 14px 0 10px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        .meta-table td.label { width: 28%; background: #fffbeb; font-weight: bold; color: #92400e; }
        .exam-block { margin-top: 16px; page-break-inside: avoid; }
        .exam-title { font-size: 12px; font-weight: bold; color: #92400e; border-bottom: 1px solid #fde68a; padding-bottom: 4px; margin-bottom: 8px; }
        table.marks { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.marks th, table.marks td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: center; font-size: 10px; }
        table.marks th { background: #f8fafc; text-transform: uppercase; }
        table.marks td.subject { text-align: left; font-weight: bold; }
        table.marks tr.total td { background: #fffbeb; font-weight: bold; }
        .exam-summary { margin-top: 6px; font-size: 10px; color: #475569; }
        .footer { margin-top: 24px; font-size: 10px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 10px; }
        .sign { margin-top: 24px; text-align: right; }
        .sign .line { border-top: 1px solid #94a3b8; width: 220px; margin-left: auto; padding-top: 4px; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @if (! empty($template['logo_data_uri']))
            <img src="{{ $template['logo_data_uri'] }}" alt="Logo" style="max-height: 56px; margin-bottom: 6px;">
        @endif
        <div class="brand">{{ $template['name'] }}</div>
        <div class="tagline">{{ $template['tagline'] }}</div>
        <div style="font-size: 10px; margin-top: 4px;">{{ $template['address'] }}</div>
    </div>

    <div class="title">Consolidated Report Card</div>

    <table class="meta-table">
        <tr>
            <td class="label">Student name</td>
            <td>{{ $student->name }}</td>
            <td class="label">Roll / Enrolment no.</td>
            <td>{{ $enrollment?->enrollment_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Programme</td>
            <td>{{ $course?->name ?? '—' }}</td>
            <td class="label">Batch</td>
            <td>{{ $batch?->name ?? '—' }}</td>
        </tr>
        @if (($template['show_attendance'] ?? false) && $attendancePercentage !== null)
            <tr>
                <td class="label">Attendance</td>
                <td colspan="3">{{ rtrim(rtrim(number_format((float) $attendancePercentage, 1), '0'), '.') }}%</td>
            </tr>
        @endif
    </table>

    @foreach ($examSections as $section)
        <div class="exam-block">
            <div class="exam-title">
                {{ $section['test_label'] }}
                · {{ $section['session_date']?->format('d M Y') ?? '—' }}
            </div>
            <table class="marks">
                <thead>
                    <tr>
                        <th style="text-align: left;">Subject</th>
                        <th>Obtained</th>
                        <th>Max</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['subjects'] as $subject)
                        @php($cell = $section['scores'][$subject] ?? null)
                        <tr>
                            <td class="subject">{{ $subject }}</td>
                            <td>{{ $cell['marks'] !== null ? rtrim(rtrim(number_format((float) $cell['marks'], 2), '0'), '.') : '—' }}</td>
                            <td>{{ $cell['max'] !== null ? rtrim(rtrim(number_format((float) $cell['max'], 2), '0'), '.') : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td class="subject">Total</td>
                        <td colspan="2">{{ $section['total']['display'] ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="exam-summary">
                <strong>%:</strong> {{ $section['percentage'] !== null ? rtrim(rtrim(number_format((float) $section['percentage'], 1), '0'), '.').'%' : '—' }}
                · <strong>Division:</strong> {{ $section['division'] ?? '—' }}
                @if (($template['show_rank'] ?? false) && ($section['rank'] ?? null))
                    · <strong>Rank:</strong> {{ $section['rank'] }}
                @endif
            </div>
            @if (($template['show_principal_remarks'] ?? false) && filled($section['principal_remarks']))
                <div class="exam-summary" style="margin-top: 4px;">
                    <strong>Remarks:</strong> {{ $section['principal_remarks'] }}
                </div>
            @endif
        </div>
    @endforeach

    <div class="footer">
        <div><strong>Report issue date:</strong> {{ is_string($issueDate) ? \Illuminate\Support\Carbon::parse($issueDate)->format('d M Y') : ($issueDate?->format('d M Y') ?? '—') }}</div>
        <div style="margin-top: 6px;">{{ $template['footer_note'] ?? '' }}</div>
    </div>

    <div class="sign">
        <div class="line">
            {{ $template['signature_title'] ?? 'Principal' }}
            @if (! empty($template['signature_name']))
                <br>{{ $template['signature_name'] }}
            @endif
        </div>
    </div>
</body>
</html>
