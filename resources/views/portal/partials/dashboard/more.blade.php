@if ($enrollment && ($classAttendancePercentage !== null || $sessionAttendanceRecords->isNotEmpty()))
    <section class="portal-card overflow-hidden">
        <div class="border-b border-navy-100 px-4 py-3.5 sm:px-5">
            <h2 class="font-display text-lg font-bold text-navy-900">Attendance</h2>
            <p class="mt-0.5 text-sm text-navy-500">Class and workshop / event attendance</p>
        </div>
        <div class="space-y-4 p-4 sm:p-5">
            @if ($classAttendancePercentage !== null)
                <div class="flex items-center gap-4 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-base font-bold text-white">
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
                    <ul class="mt-2 divide-y divide-navy-100 overflow-hidden rounded-xl border border-navy-100">
                        @foreach ($sessionAttendanceRecords as $record)
                            @php $session = $record->attendable; @endphp
                            <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
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

@include('portal.partials.change-password')

<section class="portal-card p-4 sm:p-5 lg:hidden">
    <h2 class="font-display text-base font-bold text-navy-900">Session</h2>
    <p class="mt-1 text-sm text-navy-500">Sign out of the student portal on this device.</p>
    <form method="POST" action="{{ route('portal.logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="touch-manipulation inline-flex w-full items-center justify-center gap-2 rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm font-semibold text-navy-800 shadow-sm transition hover:bg-navy-50 sm:w-auto">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            Logout
        </button>
    </form>
</section>

<div class="text-center lg:hidden">
    <a href="{{ route('home') }}" class="text-sm font-medium text-navy-500 transition hover:text-navy-800">
        ← Back to {{ $institute['name'] ?? 'website' }}
    </a>
</div>
