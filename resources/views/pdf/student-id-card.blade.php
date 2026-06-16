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
    <table width="100%" cellpadding="0" cellspacing="0" border="2" bordercolor="#b45309" style="border-collapse: collapse;">
        {{-- Header (one nested table only — DomPDF safe) --}}
        <tr>
            <td colspan="2" bgcolor="#92400e" style="padding: 5px 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="28" valign="middle" align="center">
                            @if (! empty($institute['logo_data_uri']))
                                <img src="{{ $institute['logo_data_uri'] }}" alt="Logo" width="24" height="24" style="object-fit: contain;">
                            @else
                                <table width="24" height="24" cellpadding="0" cellspacing="0" border="1" bordercolor="#fcd34d">
                                    <tr>
                                        <td align="center" valign="middle" bgcolor="#f59e0b" style="color: #ffffff; font-size: 12px; font-weight: bold;">F</td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                        <td valign="middle" style="padding-left: 5px; color: #ffffff;">
                            <span style="font-size: 11px; font-weight: bold; letter-spacing: 0.3px;">{{ strtoupper($institute['name']) }}</span><br>
                            <span style="font-size: 6px; color: #fde68a;">{{ $institute['tagline'] }}</span>
                        </td>
                        <td align="right" valign="middle" style="color: #ffffff; font-size: 8px; font-weight: bold; letter-spacing: 0.7px;">
                            IDENTITY CARD
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Body (no nested tables — keeps DomPDF rendering reliable) --}}
        <tr>
            <td width="64%" valign="top" bgcolor="#fffdf7" style="padding: 8px 10px; font-size: 7px; line-height: 1.55; border-right: 2px solid #fde68a;">
                <span style="font-size: 6px; font-weight: bold; color: #92400e;">NAME</span>
                <span style="font-size: 9px; font-weight: bold; color: #111827;"> : {{ $student->name }}</span><br>

                <span style="font-size: 6px; font-weight: bold; color: #92400e;">COURSE</span>
                <span style="color: #1f2937;"> : {{ $course?->name ?? '—' }}</span><br>

                <span style="font-size: 6px; font-weight: bold; color: #92400e;">DURATION</span>
                <span style="color: #1f2937;"> : {{ $course?->duration_label ?? '—' }}</span><br>

                <span style="font-size: 6px; font-weight: bold; color: #92400e;">FATHER</span>
                <span style="color: #1f2937;"> : {{ $student->father_name ?? '—' }}</span><br>

                <span style="font-size: 6px; font-weight: bold; color: #92400e;">D.O.B.</span>
                <span style="color: #1f2937;"> : {{ $student->date_of_birth?->format('d-m-Y') ?? '—' }}</span><br>

                <span style="font-size: 6px; font-weight: bold; color: #92400e;">PHONE</span>
                <span style="color: #1f2937;"> : {{ $student->mobile }}</span><br><br>

                <span style="font-size: 6px; font-weight: bold; color: #92400e; background-color: #fef3c7; padding: 2px 5px; border: 1px solid #fde68a;">
                    Valid from : {{ $enrollment->enrolled_at?->format('M Y') ?? '—' }}
                </span>
            </td>

            <td width="36%" valign="top" align="center" bgcolor="#ffffff" style="padding: 6px 5px;">
                <span style="font-size: 6px; font-weight: bold; color: #92400e; background-color: #fffbeb; padding: 1px 4px; border: 1px solid #fde68a;">
                    ID : {{ $enrollment->enrollment_number }}
                </span><br><br>

                @if ($photoDataUri)
                    <img src="{{ $photoDataUri }}" width="62" height="76" style="border: 2px solid #d97706; background-color: #fffbeb;"><br>
                @else
                    <span style="display: inline-block; width: 62px; height: 76px; line-height: 76px; text-align: center; border: 2px solid #d97706; background-color: #fffbeb; font-size: 6px; color: #9ca3af;">Photo</span><br>
                @endif

                <img src="{{ $qrDataUri }}" width="36" height="36" style="margin-top: 5px; border: 1px solid #e5e7eb; background-color: #ffffff; padding: 2px;"><br>
                <span style="font-size: 5px; font-weight: bold; color: #6b7280; letter-spacing: 0.3px;">SCAN TO VERIFY</span>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td bgcolor="#92400e" style="padding: 4px 8px; font-size: 5.5px; line-height: 1.35; color: #fde68a;">
                {{ $institute['address'] }}<br>
                <span style="color: #ffffff;">{{ $institute['phone'] }} · {{ $institute['email'] }}</span>
            </td>
            <td bgcolor="#92400e" align="right" valign="middle" style="padding: 4px 8px; color: #ffffff; font-size: 6px; font-weight: bold;">
                ________________<br>
                Authorised Signatory
            </td>
        </tr>
    </table>
</body>
</html>
