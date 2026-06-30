<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Marksheet — {{ $student->name }}</title>
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
        table.marks { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.marks th, table.marks td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; }
        table.marks th { background: #f8fafc; font-size: 10px; text-transform: uppercase; }
        table.marks td.subject { text-align: left; font-weight: bold; }
        table.marks tr.total td { background: #fffbeb; font-weight: bold; }
        .summary { margin-top: 14px; padding: 10px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .footer { margin-top: 24px; font-size: 10px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 10px; }
        .sign { margin-top: 30px; text-align: right; }
        .sign .line { border-top: 1px solid #94a3b8; width: 200px; margin-left: auto; padding-top: 4px; font-size: 10px; }
        .serial { float: right; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    <div class="serial">Sr. No. {{ $marksheet->formattedSerial() }}</div>

    <div class="header">
        @if (! empty($institute['logo_data_uri']))
            <img src="{{ $institute['logo_data_uri'] }}" alt="Logo" style="max-height: 56px; margin-bottom: 6px;">
        @endif
        <div class="brand">{{ $institute['name'] }}</div>
        <div class="tagline">{{ $institute['tagline'] }}</div>
        <div style="font-size: 10px; margin-top: 4px;">{{ $institute['address'] }}</div>
    </div>

    <div class="title">Statement of Marks</div>

    <table class="meta-table">
        <tr>
            <td class="label">Student name</td>
            <td>{{ $student->name }}</td>
            <td class="label">Roll / Enrolment no.</td>
            <td>{{ $enrollment?->enrollment_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Father's name</td>
            <td>{{ $student->father_name ?? '—' }}</td>
            <td class="label">Date of birth</td>
            <td>{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Programme</td>
            <td>{{ $course?->name ?? '—' }}</td>
            <td class="label">Batch</td>
            <td>{{ $batch?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Exam / Assessment</td>
            <td>{{ $testLabel }}</td>
            <td class="label">Exam date</td>
            <td>{{ $declaration->session_date?->format('d M Y') ?? '—' }}</td>
        </tr>
    </table>

    <table class="marks">
        <thead>
            <tr>
                <th style="text-align: left;">Subject</th>
                <th>Marks obtained</th>
                <th>Max marks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $subject)
                @php($cell = $scores[$subject] ?? null)
                <tr>
                    <td class="subject">{{ $subject }}</td>
                    <td>{{ $cell['marks'] !== null ? rtrim(rtrim(number_format((float) $cell['marks'], 2), '0'), '.') : '—' }}</td>
                    <td>{{ $cell['max'] !== null ? rtrim(rtrim(number_format((float) $cell['max'], 2), '0'), '.') : '—' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td class="subject">Total</td>
                <td colspan="2">{{ $total['display'] ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="summary">
        <strong>Overall percentage:</strong>
        {{ $marksheet->percentage !== null ? rtrim(rtrim(number_format((float) $marksheet->percentage, 2), '0'), '.').'%' : '—' }}
        &nbsp;·&nbsp;
        <strong>Division:</strong> {{ $marksheet->division ?? '—' }}
    </div>

    <div class="footer">
        <div><strong>Result declared on:</strong> {{ $declarationDate?->format('d M Y') ?? '—' }}</div>
        <div><strong>Marksheet issue date:</strong> {{ is_string($issueDate) ? \Illuminate\Support\Carbon::parse($issueDate)->format('d M Y') : ($issueDate?->format('d M Y') ?? '—') }}</div>
        <div style="margin-top: 6px;">This is a computer-generated marksheet. Collect the official signed copy from the institute office if required.</div>
    </div>

    <div class="sign">
        <div class="line">Controller of Examination</div>
    </div>
</body>
</html>
