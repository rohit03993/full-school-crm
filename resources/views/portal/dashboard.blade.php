@extends('layouts.portal')

@section('title', 'My Portal')
@section('heading', $student->name)
@section('subheading', $student->mobile.' · '.$student->status->label())

@section('avatar')
    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-sm font-bold text-navy-950 shadow-md ring-2 ring-white" aria-hidden="true">
        {{ $student->initials() }}
    </div>
@endsection

@section('header_actions')
    <form method="POST" action="{{ route('portal.logout') }}">
        @csrf
        <button type="submit" class="touch-manipulation inline-flex items-center justify-center gap-1.5 rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-xs font-semibold text-navy-700 shadow-sm transition hover:border-navy-300 hover:bg-navy-50 sm:px-4 sm:text-sm">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span class="hidden sm:inline">Logout</span>
        </button>
    </form>
@endsection

@section('content')
    <div class="space-y-5 sm:space-y-6">
        @if ($enrollment && $fees)
            @php
                $netFee = (float) $fees->net_fee;
                $paidAmount = (float) $fees->paid_amount;
                $pendingAmount = (float) $fees->pending_amount;
                $paidPercent = $netFee > 0 ? min(100, (int) round(($paidAmount / $netFee) * 100)) : 0;
            @endphp

            {{-- Enrollment hero --}}
            <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 via-navy-800 to-navy-900 p-5 text-white shadow-lg sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <span class="inline-flex rounded-full bg-emerald-500/20 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300 ring-1 ring-emerald-400/30">
                            Enrolled
                        </span>
                        <p class="mt-3 font-display text-xl font-bold leading-snug sm:text-2xl">{{ $enrollment->course?->name }}</p>
                        <p class="mt-2 font-mono text-sm text-navy-200">
                            {{ \App\Support\StudentLabels::rollNumberLabel() }} · {{ $enrollment->enrollment_number }}
                        </p>
                    </div>
                    <div class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20 backdrop-blur-sm">
                        <span class="text-lg font-bold leading-none text-brand-300">{{ $paidPercent }}%</span>
                        <span class="mt-0.5 text-[9px] font-semibold uppercase tracking-wide text-navy-300">Paid</span>
                    </div>
                </div>
                <div class="mt-5">
                    <div class="flex justify-between text-xs font-medium text-navy-300">
                        <span>Fee progress</span>
                        <span>₹{{ number_format($paidAmount, 0) }} / ₹{{ number_format($netFee, 0) }}</span>
                    </div>
                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-white/10">
                        <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-500 transition-all" style="width: {{ $paidPercent }}%"></div>
                    </div>
                </div>
            </section>

            {{-- Fee summary --}}
            <section class="portal-card p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-bold text-navy-900">Fee summary</h2>
                        <p class="mt-1 text-sm text-navy-500">Your course fees and payment status</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3">
                    <div class="rounded-xl bg-navy-50 p-3.5 sm:p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-navy-500">Course fee</p>
                        <p class="mt-1 text-base font-bold text-navy-900 sm:text-lg">₹{{ number_format((float) $fees->course_fee, 0) }}</p>
                    </div>
                    @if ((float) $fees->discount_amount > 0)
                        <div class="rounded-xl bg-sky-50 p-3.5 sm:p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-sky-700">Discount</p>
                            <p class="mt-1 text-base font-bold text-sky-900 sm:text-lg">−₹{{ number_format((float) $fees->discount_amount, 0) }}</p>
                        </div>
                    @endif
                    <div class="rounded-xl bg-navy-50 p-3.5 sm:p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-navy-500">Net fee</p>
                        <p class="mt-1 text-base font-bold text-navy-900 sm:text-lg">₹{{ number_format($netFee, 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3.5 ring-1 ring-emerald-100 sm:p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Paid</p>
                        <p class="mt-1 text-base font-bold text-emerald-800 sm:text-lg">₹{{ number_format($paidAmount, 0) }}</p>
                    </div>
                    <div @class([
                        'col-span-2 rounded-xl p-3.5 sm:col-span-1 sm:p-4',
                        'bg-amber-50 ring-1 ring-amber-200' => $pendingAmount > 0,
                        'bg-emerald-50 ring-1 ring-emerald-100' => $pendingAmount <= 0,
                    ])>
                        <p @class([
                            'text-[10px] font-bold uppercase tracking-wide',
                            'text-amber-800' => $pendingAmount > 0,
                            'text-emerald-700' => $pendingAmount <= 0,
                        ])>Pending</p>
                        <p @class([
                            'mt-1 text-lg font-bold sm:text-xl',
                            'text-amber-900' => $pendingAmount > 0,
                            'text-emerald-800' => $pendingAmount <= 0,
                        ])>₹{{ number_format($pendingAmount, 0) }}</p>
                    </div>
                </div>
            </section>

            @if ($enrollment->hasIdCard())
                <section class="flex flex-col gap-4 rounded-2xl border border-brand-200 bg-gradient-to-r from-brand-50 to-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Student ID card</p>
                        <p class="mt-1 text-sm text-navy-700">Download your digital ID card anytime.</p>
                    </div>
                    <a href="{{ $enrollment->portalIdCardDownloadUrl() }}"
                       class="touch-manipulation inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-bold text-navy-950 shadow-sm transition hover:bg-brand-400 active:scale-[0.99] sm:w-auto">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download ID Card
                    </a>
                </section>
            @endif

            {{-- Payment history --}}
            <section class="portal-card overflow-hidden">
                <div class="border-b border-navy-100 px-5 py-4">
                    <h2 class="font-display text-lg font-bold text-navy-900">Payment history</h2>
                    <p class="mt-0.5 text-sm text-navy-500">{{ $payments->count() }} payment{{ $payments->count() === 1 ? '' : 's' }} on record</p>
                </div>

                @if ($payments->isEmpty())
                    <div class="px-5 py-8 text-center">
                        <p class="text-sm font-medium text-navy-700">No payments yet</p>
                        <p class="mt-1 text-sm text-navy-500">Visit the institute office to pay your fees.</p>
                    </div>
                @else
                    <ul class="divide-y divide-navy-100">
                        @foreach ($payments as $payment)
                            <li class="p-4 sm:p-5">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-mono text-sm font-bold text-brand-700">{{ $payment->receipt_number }}</p>
                                        <p class="mt-1 text-sm text-navy-600">
                                            {{ $payment->payment_date->format('d M Y') }} · {{ $payment->payment_mode->label() }}
                                        </p>
                                        <p class="mt-2 text-xl font-bold text-emerald-700">₹{{ number_format((float) $payment->amount, 2) }}</p>
                                    </div>
                                    @if ($payment->hasReceiptPdf())
                                        <a href="{{ $payment->portalReceiptDownloadUrl() }}"
                                           class="touch-manipulation inline-flex w-full items-center justify-center gap-2 rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm font-semibold text-navy-800 shadow-sm transition hover:bg-navy-50 active:scale-[0.99] sm:w-auto">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            Receipt
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

        @elseif ($student->activeEnrollment)
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:p-6">
                <span class="inline-flex rounded-full bg-emerald-500/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700">Enrolled</span>
                <p class="mt-3 font-display text-xl font-bold text-emerald-900">Welcome, {{ $student->name }}!</p>
                <p class="mt-2 font-mono text-sm text-emerald-800">{{ \App\Support\StudentLabels::rollNumberLabel() }} · {{ $student->activeEnrollment->enrollment_number }}</p>
                <p class="mt-1 text-sm font-medium text-emerald-800">{{ $student->activeEnrollment->course?->name }}</p>
                <p class="mt-4 text-sm text-emerald-800/90">Fee details are being set up. Contact the office for payment queries.</p>
            </section>
        @endif

        @if (! $admission)
            <section class="portal-card p-5 sm:p-6">
                <p class="font-semibold text-navy-800">No admission started</p>
                <p class="mt-2 text-sm leading-relaxed text-navy-600">Your enquiry is with the institute. Contact the office when you are ready to proceed with admission.</p>
            </section>
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

            <section @class([
                'rounded-2xl border p-5 shadow-sm sm:p-6',
                'border-amber-200 bg-amber-50' => $statusTone === 'amber',
                'border-sky-200 bg-sky-50' => $statusTone === 'sky',
                'border-emerald-200 bg-emerald-50' => $statusTone === 'green',
                'portal-card' => $statusTone === 'navy',
            ])>
                <p class="text-xs font-bold uppercase tracking-wider text-navy-500">Admission status</p>
                <p @class([
                    'mt-1 text-lg font-bold',
                    'text-amber-900' => $statusTone === 'amber',
                    'text-sky-900' => $statusTone === 'sky',
                    'text-emerald-900' => $statusTone === 'green',
                    'text-navy-900' => $statusTone === 'navy',
                ])>{{ $status->label() }}</p>
                <p class="mt-2 font-mono text-sm font-semibold text-brand-700">{{ $admission->admission_number }}</p>
                <p class="mt-1 text-sm text-navy-600">{{ $admission->enquiry?->course?->name }}</p>

                @if ($status->value === 'verification_pending')
                    <p class="mt-3 text-sm leading-relaxed text-sky-900">Your form has been submitted. Our team is verifying your documents.</p>
                @elseif ($status->value === 'submitted' || $status->value === 'rejected')
                    <p class="mt-3 text-sm leading-relaxed text-amber-900">Please complete and submit the admission form below.</p>
                @elseif ($status->value === 'approved' && ! $student->activeEnrollment)
                    <p class="mt-3 text-sm leading-relaxed text-emerald-900">Admission approved — enrollment is being finalized.</p>
                @endif

                @if ($admission->staff_remarks)
                    <div class="mt-4 rounded-xl border border-amber-300/50 bg-white/70 p-3.5 text-sm text-amber-950">
                        <p class="font-semibold">Staff remarks</p>
                        <p class="mt-1 leading-relaxed">{{ $admission->staff_remarks }}</p>
                    </div>
                @endif
            </section>

            @if ($canFillForm)
                <form method="POST" action="{{ route('portal.admission.submit') }}" enctype="multipart/form-data" class="portal-card space-y-5 p-5 sm:space-y-6 sm:p-6">
                    @csrf

                    <div>
                        <h2 class="font-display text-lg font-bold text-navy-900">Admission form</h2>
                        <p class="mt-1 text-sm text-navy-500">Fill all fields and upload required documents.</p>
                    </div>

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
                                <label class="mb-1.5 block text-sm font-semibold text-navy-800">{{ $label }}</label>
                                <input type="{{ str_contains($name, 'percentage') ? 'number' : 'text' }}" name="{{ $name }}"
                                    value="{{ old($name, $admission->{$name}) }}" step="0.01"
                                    class="portal-input">
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
                                <label class="mb-1.5 block text-sm font-semibold text-navy-800">{{ $label }} *</label>
                                <input type="file" name="{{ $name }}" accept=".jpg,.jpeg,.png,.pdf"
                                    class="w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-navy-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-navy-800">
                                @php $existing = $admission->documents->first(fn ($d) => $d->type->value === $name); @endphp
                                @if ($existing)
                                    <p class="mt-1.5 text-xs text-navy-500">Uploaded: {{ $existing->original_filename }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @error('documents')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('admission')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                    <button type="submit" class="touch-manipulation w-full rounded-xl bg-brand-500 py-3.5 text-sm font-bold text-navy-950 shadow-sm transition hover:bg-brand-400 active:scale-[0.99]">
                        Submit admission form
                    </button>
                </form>
            @elseif (! $student->activeEnrollment)
                <section class="portal-card p-5 text-sm leading-relaxed text-navy-600 sm:p-6">
                    @if ($status->value === 'verification_pending')
                        Your admission form is under verification. You cannot edit it until staff reviews it.
                    @else
                        Your admission form is with the institute for processing. We will contact you if anything is needed.
                    @endif
                </section>
            @endif
        @endif

        @if ($enrollment && ($classAttendancePercentage !== null || $sessionAttendanceRecords->isNotEmpty()))
            <section class="portal-card overflow-hidden">
                <div class="border-b border-navy-100 px-5 py-4">
                    <h2 class="font-display text-lg font-bold text-navy-900">Attendance</h2>
                    <p class="mt-0.5 text-sm text-navy-500">Class and workshop / event attendance</p>
                </div>
                <div class="space-y-4 p-4 sm:space-y-5 sm:p-5">
                    @if ($classAttendancePercentage !== null)
                        <div class="flex items-center gap-4 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-lg font-bold text-white">
                                {{ $classAttendancePercentage }}%
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Class attendance</p>
                                <p class="mt-0.5 text-sm text-emerald-800">Overall presence in your batch</p>
                            </div>
                        </div>
                    @endif

                    @if ($sessionAttendanceRecords->isNotEmpty())
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-navy-500">Workshops &amp; events</p>
                            <ul class="mt-3 divide-y divide-navy-100 overflow-hidden rounded-xl border border-navy-100">
                                @foreach ($sessionAttendanceRecords as $record)
                                    @php $session = $record->attendable; @endphp
                                    <li class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-navy-900">{{ $session?->title ?? 'Session' }}</p>
                                            <p class="text-sm text-navy-500">
                                                {{ $session?->activityType?->name ?? 'Activity' }}
                                                · {{ $session?->session_date?->format('d M Y') ?? '—' }}
                                            </p>
                                        </div>
                                        <span @class([
                                            'inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold',
                                            'bg-emerald-100 text-emerald-800' => $record->is_present,
                                            'bg-rose-100 text-rose-800' => ! $record->is_present,
                                        ])>
                                            {{ $record->is_present ? 'Present' : 'Absent' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if (! empty($examMarksSections))
            <section class="space-y-4">
                <div>
                    <h2 class="font-display text-lg font-bold text-navy-900 sm:text-xl">Test &amp; exam marks</h2>
                    <p class="mt-1 text-sm text-navy-500">Marks recorded by your institute</p>
                </div>

                @foreach ($examMarksSections as $section)
                    <div class="portal-card overflow-hidden">
                        <div class="border-b border-navy-100 px-5 py-4">
                            <h3 class="font-semibold text-navy-900">{{ $section['label'] }}</h3>
                        </div>
                        <div class="relative">
                            <p class="px-4 pt-3 text-[10px] font-semibold uppercase tracking-wide text-navy-400 sm:hidden">Swipe to see all subjects →</p>
                            <div class="overflow-x-auto px-2 py-3 sm:px-4 sm:py-4">
                                @include('portal.partials.exam-marks-matrix', ['matrix' => $section['matrix']])
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @include('portal.partials.change-password')
    </div>
@endsection
