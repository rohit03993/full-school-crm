<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Admission — {{ $institute['name'] ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-navy-50 text-navy-900">
    <header class="border-b border-navy-100 bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Student Portal</p>
                <h1 class="font-display text-xl font-bold">{{ $student->name }}</h1>
                <p class="text-sm text-navy-500">{{ $student->mobile }} · {{ $student->status->label() }}</p>
            </div>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-navy-600 hover:text-navy-900">Logout</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        @if (session('portal_success'))
            <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-green-900">
                {{ session('portal_success') }}
            </div>
        @endif

        @if ($enrollment && $fees)
            <section class="mb-8 space-y-4">
                <div class="rounded-2xl border border-green-200 bg-green-50 p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-green-700">Enrolled</p>
                    <p class="mt-1 font-semibold text-green-900">{{ $enrollment->course?->name }}</p>
                    <p class="mt-2 font-mono text-sm text-green-800">{{ $enrollment->enrollment_number }}</p>
                </div>

                <div class="rounded-2xl border border-navy-100 bg-white p-5 shadow-sm">
                    <h2 class="font-display text-lg font-bold">Fee summary</h2>
                    <p class="mt-1 text-sm text-navy-500">Read-only view of your course fees and payments.</p>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-xl bg-navy-50 p-3">
                            <dt class="text-xs font-semibold uppercase text-navy-500">Net fee</dt>
                            <dd class="mt-1 text-lg font-bold text-navy-900">₹{{ number_format((float) $fees->net_fee, 2) }}</dd>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-3">
                            <dt class="text-xs font-semibold uppercase text-emerald-700">Paid</dt>
                            <dd class="mt-1 text-lg font-bold text-emerald-800">₹{{ number_format((float) $fees->paid_amount, 2) }}</dd>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-3">
                            <dt class="text-xs font-semibold uppercase text-amber-800">Pending</dt>
                            <dd class="mt-1 text-lg font-bold text-amber-900">₹{{ number_format((float) $fees->pending_amount, 2) }}</dd>
                        </div>
                    </dl>
                </div>

                @if ($enrollment->hasIdCard())
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-5">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Student ID card</p>
                            <p class="mt-1 text-sm text-navy-700">Your digital ID card is ready to download.</p>
                        </div>
                        <a href="{{ $enrollment->portalIdCardDownloadUrl() }}"
                           class="inline-flex rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-navy-950 hover:bg-brand-400">
                            Download ID Card
                        </a>
                    </div>
                @endif

                <div class="rounded-2xl border border-navy-100 bg-white shadow-sm">
                    <div class="border-b border-navy-100 px-5 py-4">
                        <h2 class="font-display text-lg font-bold">Payment history</h2>
                    </div>
                    @if ($payments->isEmpty())
                        <p class="px-5 py-6 text-sm text-navy-500">No payments recorded yet. Visit the institute office to pay fees.</p>
                    @else
                        <ul class="divide-y divide-navy-100">
                            @foreach ($payments as $payment)
                                <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="font-mono text-sm font-bold text-brand-700">{{ $payment->receipt_number }}</p>
                                        <p class="mt-0.5 text-sm text-navy-600">
                                            {{ $payment->payment_date->format('d M Y') }} · {{ $payment->payment_mode->label() }}
                                        </p>
                                        <p class="mt-1 text-lg font-bold text-emerald-700">₹{{ number_format((float) $payment->amount, 2) }}</p>
                                    </div>
                                    @if ($payment->hasReceiptPdf())
                                        <a href="{{ $payment->portalReceiptDownloadUrl() }}"
                                           class="inline-flex shrink-0 justify-center rounded-xl border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 hover:bg-navy-50">
                                            Download receipt
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @elseif ($student->activeEnrollment)
            <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-green-700">Enrolled</p>
                <p class="mt-1 font-semibold text-green-900">Welcome! Your admission is approved.</p>
                <p class="mt-2 font-mono text-sm text-green-800">{{ $student->activeEnrollment->enrollment_number }}</p>
                <p class="mt-1 text-sm text-green-800">{{ $student->activeEnrollment->course?->name }}</p>
                <p class="mt-3 text-sm text-green-800">Fee details are being set up. Contact the office for payment queries.</p>
            </div>
        @endif

        @if (! $admission)
            <div class="rounded-2xl border border-navy-100 bg-white p-6 shadow-sm">
                <p class="font-semibold text-navy-800">No admission started</p>
                <p class="mt-2 text-navy-600">Your enquiry is with the institute. Contact the office when you are ready to proceed with admission.</p>
            </div>
        @else
            @php
                $status = $admission->status;
                $statusTone = match ($status->value) {
                    'submitted', 'rejected' => 'amber',
                    'verification_pending' => 'sky',
                    'approved' => 'green',
                    default => 'navy',
                };
            @endphp

            <div @class([
                'mb-6 rounded-2xl border p-5 shadow-sm',
                'border-amber-200 bg-amber-50' => $statusTone === 'amber',
                'border-sky-200 bg-sky-50' => $statusTone === 'sky',
                'border-green-200 bg-green-50' => $statusTone === 'green',
                'border-navy-100 bg-white' => $statusTone === 'navy',
            ])>
                <p class="text-xs font-bold uppercase tracking-wider text-navy-500">Admission status</p>
                <p @class([
                    'mt-1 text-lg font-bold',
                    'text-amber-900' => $statusTone === 'amber',
                    'text-sky-900' => $statusTone === 'sky',
                    'text-green-900' => $statusTone === 'green',
                    'text-navy-900' => $statusTone === 'navy',
                ])>{{ $status->label() }}</p>
                <p class="mt-2 font-mono text-sm font-semibold text-brand-700">{{ $admission->admission_number }}</p>
                <p class="mt-1 text-sm text-navy-600">{{ $admission->enquiry?->course?->name }}</p>

                @if ($status->value === 'verification_pending')
                    <p class="mt-3 text-sm text-sky-900">Your form has been submitted. Our team is verifying your documents. We will contact you if anything is needed.</p>
                @elseif ($status->value === 'submitted' || $status->value === 'rejected')
                    <p class="mt-3 text-sm text-amber-900">Please complete and submit the admission form below.</p>
                @elseif ($status->value === 'approved' && ! $student->activeEnrollment)
                    <p class="mt-3 text-sm text-green-900">Admission approved — enrollment is being finalized.</p>
                @endif

                @if ($admission->staff_remarks)
                    <div class="mt-4 rounded-xl border border-amber-300/50 bg-white/70 p-3 text-sm text-amber-950">
                        <p class="font-semibold">Staff remarks</p>
                        <p class="mt-1">{{ $admission->staff_remarks }}</p>
                    </div>
                @endif
            </div>

            @if ($canFillForm)
                <form method="POST" action="{{ route('portal.admission.submit') }}" enctype="multipart/form-data" class="space-y-6 rounded-2xl border border-navy-100 bg-white p-6 shadow-sm">
                    @csrf

                    <h2 class="font-display text-lg font-bold">Admission Form</h2>
                    <p class="text-sm text-navy-500">Fill all fields and upload required documents. Password for this portal is your date of birth as DDMMYYYY.</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ([
                            ['tenth_board', '10th Board'],
                            ['tenth_percentage', '10th %'],
                            ['twelfth_board', '12th Board'],
                            ['twelfth_percentage', '12th %'],
                            ['graduation', 'Graduation'],
                            ['graduation_percentage', 'Graduation %'],
                        ] as [$name, $label])
                            <div>
                                <label class="mb-1 block text-sm font-semibold">{{ $label }}</label>
                                <input type="{{ str_contains($name, 'percentage') ? 'number' : 'text' }}" name="{{ $name }}"
                                    value="{{ old($name, $admission->{$name}) }}" step="0.01"
                                    class="w-full rounded-xl border border-navy-200 px-4 py-2.5">
                            </div>
                        @endforeach
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ([
                            ['photo', 'Student Photo'],
                            ['aadhaar', 'Aadhaar Card'],
                            ['marksheet', 'Marksheet'],
                            ['signature', 'Signature'],
                        ] as [$name, $label])
                            <div>
                                <label class="mb-1 block text-sm font-semibold">{{ $label }} *</label>
                                <input type="file" name="{{ $name }}" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                                @php $existing = $admission->documents->first(fn ($d) => $d->type->value === $name); @endphp
                                @if ($existing)
                                    <p class="mt-1 text-xs text-navy-500">Uploaded: {{ $existing->original_filename }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @error('documents')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('admission')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                    <button type="submit" class="w-full rounded-xl bg-brand-500 py-3.5 font-bold text-navy-950 hover:bg-brand-400">
                        Submit Admission Form
                    </button>
                </form>
            @elseif (! $student->activeEnrollment)
                <div class="rounded-2xl border border-navy-100 bg-white p-6 text-navy-600 shadow-sm">
                    @if ($status->value === 'verification_pending')
                        Your admission form is under verification. You cannot edit it until staff reviews it.
                    @else
                        Your admission form is with the institute for processing. We will contact you if anything is needed.
                    @endif
                </div>
            @endif
        @endif
    </main>
</body>
</html>
