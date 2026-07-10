<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $enrollment->enrollment_number }} — ID Card</title>
    <style>
        /* Exact CR80 size — must stay on ONE page */
        @page { margin: 0; size: 85.6mm 54mm; }
        * { margin: 0; padding: 0; }
        html, body {
            margin: 0;
            padding: 0;
            width: 85.6mm;
            height: 54mm;
            overflow: hidden;
            font-family: DejaVu Sans, sans-serif;
        }
        .card {
            width: 85.6mm;
            height: 54mm;
            overflow: hidden;
            border-collapse: collapse;
        }
        .label { font-size: 6px; font-weight: bold; color: #ffffff; }
        .value { font-size: 6.5px; color: #ffffff; }
    </style>
</head>
<body>
@php
    $primary = $institute['id_card_primary'] ?? '#1e40af';
    $primaryDark = $institute['id_card_primary_dark'] ?? '#1e3a8a';
    $accent = $institute['id_card_accent'] ?? '#dc2626';
    $badge = $institute['id_card_badge'] ?? '#fbbf24';
    $headerText = $institute['id_card_header_text'] ?? '#1e3a8a';

    $instituteName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($institute['name'] ?? '', 58, '…'));
    $studentName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($student->name ?? '', 28, '…'));
    $courseName = \Illuminate\Support\Str::limit($course?->name ?? '—', 32, '…');
    $fatherName = \Illuminate\Support\Str::limit($student->father_name ?: '—', 28, '…');
    $duration = \Illuminate\Support\Str::limit($course?->duration_label ?? '—', 16, '…');
    $session = \Illuminate\Support\Str::limit($sessionName ?: '—', 16, '…');
    $batch = filled($batchLabel) ? \Illuminate\Support\Str::limit($batchLabel, 22, '…') : null;
    $roll = $enrollment->enrollment_number;
@endphp

<table class="card" width="243" height="153" cellpadding="0" cellspacing="0" border="0" bgcolor="{{ $primary }}">
    {{-- Header: white bar like MJPITM --}}
    <tr>
        <td colspan="2" height="34" bgcolor="#ffffff" style="padding: 3px 6px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="30" valign="middle" align="center">
                        @if (! empty($institute['logo_data_uri']))
                            <img src="{{ $institute['logo_data_uri'] }}" width="24" height="24" alt="">
                        @else
                            <span style="display:inline-block;width:24px;height:24px;line-height:24px;text-align:center;background:{{ $primary }};color:#fff;font-size:10px;font-weight:bold;">
                                {{ mb_strtoupper(mb_substr($institute['name'], 0, 1)) }}
                            </span>
                        @endif
                    </td>
                    <td valign="middle" style="padding-left: 5px;">
                        <div style="font-size: 7px; font-weight: bold; color: {{ $headerText }}; line-height: 1.15; text-transform: uppercase;">
                            {{ $instituteName }}
                        </div>
                        <div style="font-size: 6.5px; font-weight: bold; color: {{ $accent }}; margin-top: 1px; letter-spacing: 0.3px;">
                            STUDENT IDENTITY CARD
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Body: photo + details (fixed height so footer never spills) --}}
    <tr>
        <td width="78" height="100" valign="middle" align="center" bgcolor="{{ $primary }}" style="padding: 4px 3px;">
            @if ($photoDataUri)
                <img src="{{ $photoDataUri }}" width="58" height="70" alt="" style="border: 1.5px solid #ffffff;">
            @else
                <div style="width:58px;height:70px;border:1.5px solid #ffffff;background:#ffffff;line-height:70px;text-align:center;font-size:7px;color:#9ca3af;">
                    Photo
                </div>
            @endif
        </td>
        <td height="100" valign="top" bgcolor="{{ $primary }}" style="padding: 4px 6px 2px 2px;">
            <div style="font-size: 11px; font-weight: bold; color: #ffffff; line-height: 1.1; text-transform: uppercase;">
                {{ $studentName }}
            </div>

            <div style="margin-top: 3px; margin-bottom: 4px;">
                <span style="background-color: {{ $badge }}; color: #111827; font-size: 6.5px; font-weight: bold; padding: 1px 5px;">
                    {{ strtoupper($rollLabel) }}: {{ $roll }}
                </span>
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td class="label" width="48%">DURATION: <span class="value">{{ $duration }}</span></td>
                    <td class="label">SESSION: <span class="value">{{ $session }}</span></td>
                </tr>
                @if ($batch)
                    <tr>
                        <td colspan="2" style="padding-top: 2px;" class="label">BATCH: <span class="value">{{ $batch }}</span></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="2" style="padding-top: 2px;" class="label">FATHER'S NAME: <span class="value">{{ $fatherName }}</span></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding-top: 2px;" class="label">COURSE: <span class="value">{{ $courseName }}</span></td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Thin footer — phone + tiny QR (kept short to avoid page 2) --}}
    <tr>
        <td colspan="2" height="19" bgcolor="{{ $primaryDark }}" style="padding: 1px 5px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle" style="font-size: 5px; color: #e5e7eb;">
                        {{ \Illuminate\Support\Str::limit(trim(($institute['phone'] ?? '').'  '.($institute['email'] ?? '')), 55, '…') }}
                    </td>
                    <td width="22" align="right" valign="middle">
                        <img src="{{ $qrDataUri }}" width="16" height="16" alt="" style="background:#ffffff;">
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
