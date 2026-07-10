<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $enrollment->enrollment_number }} — ID Card</title>
    <style>
        @page { margin: 0; size: 243pt 153pt; }
        html, body {
            margin: 0;
            padding: 0;
            width: 243pt;
            height: 153pt;
            overflow: hidden;
            font-family: DejaVu Sans, sans-serif;
        }
        .card {
            position: relative;
            width: 243pt;
            height: 153pt;
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: avoid;
        }
        .header {
            position: absolute;
            top: 0;
            left: 0;
            width: 243pt;
            height: 32pt;
            background: #ffffff;
        }
        .body {
            position: absolute;
            top: 32pt;
            left: 0;
            width: 243pt;
            height: 121pt;
            background: {{ $institute['id_card_primary'] ?? '#1e40af' }};
        }
        .photo {
            position: absolute;
            top: 38pt;
            left: 8pt;
            width: 52pt;
            height: 64pt;
            border: 1.5pt solid #ffffff;
            background: #ffffff;
        }
        .details {
            position: absolute;
            top: 36pt;
            left: 68pt;
            width: 168pt;
            height: 100pt;
            color: #ffffff;
        }
        .qr {
            position: absolute;
            right: 6pt;
            bottom: 6pt;
            width: 18pt;
            height: 18pt;
            background: #ffffff;
        }
    </style>
</head>
<body>
@php
    $primary = $institute['id_card_primary'] ?? '#1e40af';
    $accent = $institute['id_card_accent'] ?? '#dc2626';
    $badge = $institute['id_card_badge'] ?? '#fbbf24';
    $headerText = $institute['id_card_header_text'] ?? '#1e3a8a';

    $instituteName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($institute['name'] ?? '', 52, ''));
    $studentName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($student->name ?? '', 26, ''));
    $courseName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($course?->name ?? '—', 30, ''));
    $fatherName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($student->father_name ?: '—', 26, ''));
    $duration = \Illuminate\Support\Str::limit($course?->duration_label ?? '—', 14, '');
    $session = \Illuminate\Support\Str::limit($sessionName ?: '—', 14, '');
    $batch = filled($batchLabel) ? \Illuminate\Support\Str::limit($batchLabel, 20, '') : null;
    $roll = $enrollment->enrollment_number;
@endphp

{{-- Absolute layout in points — DomPDF cannot grow past one CR80 page --}}
<div class="card" style="background: {{ $primary }};">
    <div class="header">
        <table width="100%" cellpadding="0" cellspacing="0" style="height: 32pt;">
            <tr>
                <td width="28" valign="middle" align="center" style="padding-left: 5pt;">
                    @if (! empty($institute['logo_data_uri']))
                        <img src="{{ $institute['logo_data_uri'] }}" width="22" height="22" alt="">
                    @else
                        <span style="color: {{ $primary }}; font-size: 12pt; font-weight: bold;">
                            {{ mb_strtoupper(mb_substr($institute['name'], 0, 1)) }}
                        </span>
                    @endif
                </td>
                <td valign="middle" style="padding-left: 4pt; padding-right: 6pt;">
                    <div style="font-size: 7pt; font-weight: bold; color: {{ $headerText }}; line-height: 1.1; text-transform: uppercase;">
                        {{ $instituteName }}
                    </div>
                    <div style="font-size: 6.5pt; font-weight: bold; color: {{ $accent }}; margin-top: 1pt;">
                        STUDENT IDENTITY CARD
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body" style="background: {{ $primary }};"></div>

    @if ($photoDataUri)
        <img class="photo" src="{{ $photoDataUri }}" alt="">
    @else
        <div class="photo" style="text-align: center; line-height: 64pt; font-size: 7pt; color: #9ca3af;">Photo</div>
    @endif

    <div class="details">
        <div style="font-size: 11pt; font-weight: bold; line-height: 1.05; text-transform: uppercase;">
            {{ $studentName }}
        </div>

        <div style="margin-top: 3pt; margin-bottom: 4pt;">
            <span style="background: {{ $badge }}; color: #111827; font-size: 6.5pt; font-weight: bold; padding: 1pt 4pt;">
                {{ strtoupper($rollLabel) }}: {{ $roll }}
            </span>
        </div>

        <div style="font-size: 6pt; line-height: 1.45; font-weight: bold;">
            DURATION: <span style="font-weight: normal;">{{ $duration }}</span>
            &nbsp;&nbsp;
            SESSION: <span style="font-weight: normal;">{{ $session }}</span>
        </div>
        @if ($batch)
            <div style="font-size: 6pt; line-height: 1.45; font-weight: bold;">
                BATCH: <span style="font-weight: normal;">{{ $batch }}</span>
            </div>
        @endif
        <div style="font-size: 6pt; line-height: 1.45; font-weight: bold;">
            FATHER'S NAME: <span style="font-weight: normal;">{{ $fatherName }}</span>
        </div>
        <div style="font-size: 6pt; line-height: 1.45; font-weight: bold;">
            COURSE: <span style="font-weight: normal;">{{ $courseName }}</span>
        </div>
    </div>

    <img class="qr" src="{{ $qrDataUri }}" alt="">
</div>
</body>
</html>
