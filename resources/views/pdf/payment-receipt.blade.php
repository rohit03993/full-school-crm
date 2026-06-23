<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 24px; }
        .header { border-bottom: 2px solid #d97706; padding-bottom: 12px; margin-bottom: 18px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .logo { width: 56px; height: 56px; object-fit: contain; }
        .brand { font-size: 22px; font-weight: bold; color: #b45309; }
        .receipt-header { font-size: 11px; color: #92400e; margin-top: 2px; font-weight: bold; }
        .tagline { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .meta { margin-top: 8px; font-size: 10px; color: #4b5563; }
        .title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 1px; margin: 16px 0; text-transform: uppercase; }
        .receipt-no { text-align: center; font-size: 13px; color: #b45309; font-weight: bold; margin-bottom: 18px; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.details td { padding: 7px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        table.details td.label { width: 34%; background: #fffbeb; font-weight: bold; color: #92400e; }
        .amount-box { border: 2px solid #d97706; background: #fffbeb; padding: 14px; margin: 18px 0; text-align: center; }
        .amount-box .value { font-size: 24px; font-weight: bold; color: #b45309; }
        .amount-box .words { margin-top: 6px; font-size: 11px; color: #374151; font-style: italic; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px dashed #d1d5db; font-size: 10px; color: #6b7280; }
        .sign { margin-top: 36px; text-align: right; }
        .sign .line { border-top: 1px solid #9ca3af; width: 180px; margin-left: auto; padding-top: 4px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                @if (! empty($institute['logo_data_uri']))
                    <td style="width: 64px; vertical-align: top;">
                        <img src="{{ $institute['logo_data_uri'] }}" alt="Logo" class="logo">
                    </td>
                @endif
                <td style="vertical-align: top;">
                    <div class="brand">{{ $institute['name'] }}</div>
                    <div class="tagline">{{ $institute['tagline'] }}</div>
                    @if (! empty($institute['receipt_header']))
                        <div class="receipt-header">{{ $institute['receipt_header'] }}</div>
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

    <div class="title">Fee Receipt</div>
    <div class="receipt-no">{{ $payment->receipt_number }}</div>

    <table class="details">
        <tr>
            <td class="label">Student Name</td>
            <td>{{ $student->name }}</td>
        </tr>
        <tr>
            <td class="label">{{ \App\Support\StudentLabels::rollNumberLabel() }}</td>
            <td>{{ $enrollment->enrollment_number }}</td>
        </tr>
        <tr>
            <td class="label">Course</td>
            <td>{{ $course?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Payment Date</td>
            <td>{{ $payment->payment_date->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Payment Mode</td>
            <td>{{ $payment->payment_mode->label() }}@if ($modeReference) · {{ $modeReference }} @endif</td>
        </tr>
        <tr>
            <td class="label">Received By</td>
            <td>{{ $collector?->staffCollectorLabel() ?? 'Staff' }}</td>
        </tr>
    </table>

    <div class="amount-box">
        <div>Amount Received</div>
        <div class="value">₹{{ number_format((float) $payment->amount, 2) }}</div>
        <div class="words">{{ $amountInWords }}</div>
    </div>

    <div class="sign">
        <div class="line">Authorised Signatory<br>{{ $institute['name'] }}</div>
    </div>

    @if ($footer)
        <div class="footer">{{ $footer }}</div>
    @endif
</body>
</html>
