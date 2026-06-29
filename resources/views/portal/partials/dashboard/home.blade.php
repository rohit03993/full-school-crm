@php
    $netFee = $enrollment && $fees ? (float) $fees->net_fee : 0;
    $paidAmount = $enrollment && $fees ? (float) $fees->paid_amount : 0;
    $pendingAmount = $enrollment && $fees ? (float) $fees->pending_amount : 0;
    $paidPercent = $netFee > 0 ? min(100, (int) round(($paidAmount / $netFee) * 100)) : 0;
    $hasMarks = ! empty($examMarksSections);
    $hasAttendance = $enrollment && ($classAttendancePercentage !== null || $sessionAttendanceRecords->isNotEmpty());
@endphp

<div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-5 lg:gap-6">
    <div class="space-y-4 lg:col-span-3">
@if ($enrollment && $fees)
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 via-navy-800 to-navy-900 p-4 text-white shadow-lg sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <span class="inline-flex rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-300 ring-1 ring-emerald-400/30">
                    Enrolled
                </span>
                <p class="mt-2 font-display text-lg font-bold leading-snug sm:text-xl">{{ $enrollment->course?->name }}</p>
                <p class="mt-1 font-mono text-xs text-navy-200 sm:text-sm">
                    {{ \App\Support\StudentLabels::rollNumberLabel() }} · {{ $enrollment->enrollment_number }}
                </p>
            </div>
            <div class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                <span class="text-base font-bold leading-none text-brand-300">{{ $paidPercent }}%</span>
                <span class="mt-0.5 text-[8px] font-semibold uppercase tracking-wide text-navy-300">Paid</span>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-[11px] font-medium text-navy-300">
                <span>Fee progress</span>
                <span>₹{{ number_format($paidAmount, 0) }} / ₹{{ number_format($netFee, 0) }}</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-white/10">
                <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-500" style="width: {{ $paidPercent }}%"></div>
            </div>
        </div>
    </section>
@elseif ($student->activeEnrollment)
    <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:p-5">
        <span class="inline-flex rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">Enrolled</span>
        <p class="mt-2 font-display text-lg font-bold text-emerald-900">Welcome, {{ $student->name }}!</p>
        <p class="mt-1 font-mono text-sm text-emerald-800">{{ \App\Support\StudentLabels::rollNumberLabel() }} · {{ $student->activeEnrollment->enrollment_number }}</p>
        <p class="mt-1 text-sm font-medium text-emerald-800">{{ $student->activeEnrollment->course?->name }}</p>
    </section>
@endif

@if ($admission && ! $enrollment)
    <section class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
        <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Admission in progress</p>
        <p class="mt-1 font-semibold text-sky-900">{{ $admission->status->label() }}</p>
        <button type="button" @click="setTab('admission')" class="touch-manipulation mt-3 text-sm font-semibold text-sky-800 underline underline-offset-2">
            View admission details →
        </button>
    </section>
@endif

    </div>

    <div class="lg:col-span-2">
<div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-1 lg:gap-3 xl:grid-cols-2">
    @if ($enrollment)
        <a href="{{ route('portal.homework.index') }}" class="portal-quick-action relative">
            @if (($portalNav['homeworkBadge'] ?? 0) > 0)
                <span class="absolute right-2 top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">
                    {{ ($portalNav['homeworkBadge'] ?? 0) > 9 ? '9+' : $portalNav['homeworkBadge'] }}
                </span>
            @endif
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            </span>
            <span class="text-xs font-bold text-navy-900 sm:text-sm">Homework</span>
        </a>

        @if ($fees)
            <button type="button" @click="setTab('fees')" class="portal-quick-action text-left">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </span>
                <span class="text-xs font-bold text-navy-900 sm:text-sm">Fees</span>
                @if ($pendingAmount > 0)
                    <span class="text-[10px] font-semibold text-amber-700">₹{{ number_format($pendingAmount, 0) }} due</span>
                @else
                    <span class="text-[10px] font-semibold text-emerald-700">All paid</span>
                @endif
            </button>
        @endif

        @if ($hasMarks)
            <button type="button" @click="setTab('marks')" class="portal-quick-action text-left">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                </span>
                <span class="text-xs font-bold text-navy-900 sm:text-sm">Marks</span>
            </button>
        @endif

        @if ($enrollment->hasIdCard())
            <a href="{{ $enrollment->portalIdCardDownloadUrl() }}" class="portal-quick-action">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" /></svg>
                </span>
                <span class="text-xs font-bold text-navy-900 sm:text-sm">ID Card</span>
            </a>
        @endif

        @if ($hasAttendance)
            <button type="button" @click="setTab('more')" class="portal-quick-action text-left">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </span>
                <span class="text-xs font-bold text-navy-900 sm:text-sm">Attendance</span>
                @if ($classAttendancePercentage !== null)
                    <span class="text-[10px] font-semibold text-sky-700">{{ $classAttendancePercentage }}% present</span>
                @endif
            </button>
        @endif
    @endif

    <button type="button" @click="setTab('more')" class="portal-quick-action text-left">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-100 text-navy-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
        </span>
        <span class="text-xs font-bold text-navy-900 sm:text-sm">Account</span>
    </button>
</div>
    </div>
</div>
