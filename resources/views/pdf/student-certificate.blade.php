<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $certificate->serial_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 36px 42px; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 14px; margin-bottom: 22px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .logo { width: 64px; height: 64px; object-fit: contain; }
        .brand { font-size: 22px; font-weight: bold; color: #1e3a8a; }
        .tagline { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .meta { margin-top: 8px; font-size: 10px; color: #4b5563; }
        .title { text-align: center; font-size: 18px; font-weight: bold; letter-spacing: 1.5px; margin: 22px 0 8px; text-transform: uppercase; color: #111827; }
        .serial { text-align: center; font-size: 11px; color: #1e40af; font-weight: bold; margin-bottom: 22px; }
        .body { font-size: 13px; line-height: 1.7; text-align: justify; margin: 18px 0 24px; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.details td { padding: 7px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        table.details td.label { width: 32%; background: #eff6ff; font-weight: bold; color: #1e3a8a; }
        .remarks { margin-top: 12px; font-size: 11px; color: #374151; }
        .footer { margin-top: 40px; padding-top: 12px; border-top: 1px dashed #d1d5db; font-size: 10px; color: #6b7280; }
        .sign { margin-top: 48px; text-align: right; }
        .sign .line { border-top: 1px solid #9ca3af; width: 200px; margin-left: auto; padding-top: 4px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                @if (! empty($institute['logo_data_uri']))
                    <td style="width: 72px; vertical-align: top;">
                        <img src="{{ $institute['logo_data_uri'] }}" alt="Logo" class="logo">
                    </td>
                @endif
                <td style="vertical-align: top;">
                    <div class="brand">{{ $institute['name'] }}</div>
                    @if (! empty($institute['tagline']))
                        <div class="tagline">{{ $institute['tagline'] }}</div>
                    @endif
                    <div class="meta">
                        {{ $institute['address'] }}
                        @if ($institute['phone']) · {{ $institute['phone'] }} @endif
                        @if ($institute['email']) · {{ $institute['email'] }} @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">{{ $title }}</div>
    <div class="serial">{{ $certificate->serial_number }}</div>

    <p class="body">{{ $body }}</p>

    <table class="details">
        <tr>
            <td class="label">Student Name</td>
            <td>{{ $student?->name ?? ($certificate->snapshot['student_name'] ?? '—') }}</td>
        </tr>
        @if (filled($student?->father_name ?? ($certificate->snapshot['father_name'] ?? null)))
            <tr>
                <td class="label">Father / Guardian</td>
                <td>{{ $student?->father_name ?? $certificate->snapshot['father_name'] }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">{{ $rollLabel }}</td>
            <td>{{ $enrollment?->enrollment_number ?? ($certificate->snapshot['enrollment_number'] ?? '—') }}</td>
        </tr>
        <tr>
            <td class="label">Course / Programme</td>
            <td>{{ $courseName ?? '—' }}</td>
        </tr>
        @if (filled($batchLabel))
            <tr>
                <td class="label">Class / Batch</td>
                <td>{{ $batchLabel }}</td>
            </tr>
        @endif
        @if (filled($sessionName))
            <tr>
                <td class="label">Academic Session</td>
                <td>{{ $sessionName }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Date of Issue</td>
            <td>{{ $certificate->issued_on?->format('d M Y') }}</td>
        </tr>
    </table>

    @if (filled($certificate->remarks))
        <p class="remarks"><strong>Remarks:</strong> {{ $certificate->remarks }}</p>
    @endif

    <div class="sign">
        <div class="line">Authorised Signatory</div>
    </div>

    <div class="footer">
        {{ $institute['footer'] ?: 'This is a computer-generated certificate. Collect a signed copy from the institute office if required.' }}
        @if ($certificate->issuedBy)
            · Issued by {{ $certificate->issuedBy->name }}
        @endif
    </div>
</body>
</html>
