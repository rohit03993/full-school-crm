<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $enrollment->enrollment_number }} — ID Card</title>
    <style>
        @page { margin: 0; size: 86mm 54mm; }
        body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }
    </style>
</head>
<body>
@php
    $primary = $institute['id_card_primary'] ?? '#1e40af';
    $primaryDark = $institute['id_card_primary_dark'] ?? '#1e3a8a';
    $accent = $institute['id_card_accent'] ?? '#dc2626';
    $badge = $institute['id_card_badge'] ?? '#fbbf24';
    $headerText = $institute['id_card_header_text'] ?? '#1e3a8a';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; background-color: {{ $primary }};">
    {{-- White institute header (MJPITM-style) --}}
    <tr>
        <td colspan="2" bgcolor="#ffffff" style="padding: 5px 8px; border-bottom: 2px solid {{ $primaryDark }};">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="34" valign="middle" align="center">
                        @if (! empty($institute['logo_data_uri']))
                            <img src="{{ $institute['logo_data_uri'] }}" alt="Logo" width="28" height="28" style="object-fit: contain;">
                        @else
                            <table width="28" height="28" cellpadding="0" cellspacing="0" bgcolor="{{ $primary }}">
                                <tr>
                                    <td align="center" valign="middle" style="color: #ffffff; font-size: 11px; font-weight: bold;">
                                        {{ mb_strtoupper(mb_substr($institute['name'], 0, 1)) }}
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </td>
                    <td valign="middle" style="padding-left: 6px;">
                        <div style="font-size: 8px; font-weight: bold; color: {{ $headerText }}; line-height: 1.2; text-transform: uppercase;">
                            {{ $institute['name'] }}
                        </div>
                        <div style="font-size: 7px; font-weight: bold; color: {{ $accent }}; letter-spacing: 0.4px; margin-top: 2px;">
                            STUDENT IDENTITY CARD
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Body: photo + details on brand colour --}}
    <tr>
        <td width="28%" valign="middle" align="center" bgcolor="{{ $primary }}" style="padding: 8px 6px;">
            @if ($photoDataUri)
                <img src="{{ $photoDataUri }}" width="68" height="78" style="border: 2px solid #ffffff; background-color: #ffffff;">
            @else
                <table width="68" height="78" cellpadding="0" cellspacing="0" border="1" bordercolor="#ffffff" bgcolor="#ffffff">
                    <tr>
                        <td align="center" valign="middle" style="font-size: 7px; color: #9ca3af;">Photo</td>
                    </tr>
                </table>
            @endif
        </td>
        <td width="72%" valign="top" bgcolor="{{ $primary }}" style="padding: 7px 8px 6px 4px; color: #ffffff;">
            <div style="font-size: 12px; font-weight: bold; line-height: 1.15; text-transform: uppercase; letter-spacing: 0.2px;">
                {{ $student->name }}
            </div>

            <div style="margin-top: 4px; margin-bottom: 5px;">
                <span style="display: inline-block; background-color: {{ $badge }}; color: #111827; font-size: 7px; font-weight: bold; padding: 2px 6px;">
                    {{ strtoupper($rollLabel) }}: {{ $enrollment->enrollment_number }}
                </span>
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 6.5px; line-height: 1.55; color: #ffffff;">
                <tr>
                    <td width="34%" style="font-weight: bold; opacity: 0.9;">DURATION</td>
                    <td>: {{ $course?->duration_label ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; opacity: 0.9;">SESSION</td>
                    <td>: {{ $sessionName ?: '—' }}</td>
                </tr>
                @if (filled($batchLabel))
                    <tr>
                        <td style="font-weight: bold; opacity: 0.9;">BATCH</td>
                        <td>: {{ $batchLabel }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="font-weight: bold; opacity: 0.9;">FATHER'S NAME</td>
                    <td>: {{ $student->father_name ?: '—' }}</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; opacity: 0.9;">COURSE</td>
                    <td>: {{ $course?->name ?? '—' }}</td>
                </tr>
                @if (filled($validTill))
                    <tr>
                        <td style="font-weight: bold; opacity: 0.9;">VALID TILL</td>
                        <td>: {{ $validTill }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>

    {{-- Footer strip --}}
    <tr>
        <td colspan="2" bgcolor="{{ $primaryDark }}" style="padding: 3px 8px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle" style="font-size: 5px; color: #e5e7eb; line-height: 1.3;">
                        {{ $institute['phone'] }}
                        @if (filled($institute['email']))
                            · {{ $institute['email'] }}
                        @endif
                    </td>
                    <td width="42" align="right" valign="middle">
                        <img src="{{ $qrDataUri }}" width="28" height="28" style="background-color: #ffffff; padding: 1px;">
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
