@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\BatchStudent> $roster */
    /** @var array<int, array{status: string, checked_in_at: ?string, checked_out_at: ?string, is_inside: bool}> $attendanceSnapshot */
    $pendingCount = $roster->filter(function ($row) use ($attendanceSnapshot): bool {
        return blank($attendanceSnapshot[$row->student_id]['checked_in_at'] ?? null);
    })->count();
@endphp

<div wire:key="manual-attendance-roster" class="space-y-4">
    <div class="fi-section rounded-2xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-bold text-gray-950 dark:text-white">Tap IN when a student arrives · OUT when they leave</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Each tap saves immediately and sends parent WhatsApp (when configured). No separate save step.
                </p>
            </div>
            @if ($pendingCount > 0)
                <button
                    type="button"
                    wire:click="checkInAllStudents"
                    wire:loading.attr="disabled"
                    wire:target="checkInAllStudents"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="checkInAllStudents">Check in all ({{ $pendingCount }})</span>
                    <span wire:loading wire:target="checkInAllStudents">Checking in…</span>
                </button>
            @endif
        </div>
    </div>

    <div class="fi-section overflow-hidden rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($roster as $row)
                @php
                    $student = $row->student;
                    $snapshot = $attendanceSnapshot[$student->id] ?? null;
                    $checkedIn = $snapshot['checked_in_at'] ?? null;
                    $checkedOut = $snapshot['checked_out_at'] ?? null;
                    $isInside = (bool) ($snapshot['is_inside'] ?? false);
                @endphp
                <div
                    wire:key="manual-student-{{ $student->id }}"
                    @class([
                        'flex flex-col gap-4 px-4 py-4 transition sm:flex-row sm:items-center sm:justify-between sm:px-5',
                        'bg-white dark:bg-gray-900' => ! $isInside && $checkedIn === null,
                        'bg-emerald-50/40 dark:bg-emerald-500/5' => $isInside,
                        'bg-gray-50/80 dark:bg-white/[0.03]' => $checkedOut !== null,
                    ])
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span @class([
                            'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-bold',
                            'bg-primary-500/10 text-primary-700 dark:text-primary-300' => $checkedIn === null,
                            'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' => $isInside,
                            'bg-gray-200/80 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $checkedOut !== null,
                        ])>
                            {{ strtoupper(substr($student->name, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-base font-bold text-gray-950 dark:text-white">{{ $student->name }}</p>
                            @if (filled($student->mobile))
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->mobile }}</p>
                            @else
                                <p class="text-xs text-amber-600 dark:text-amber-400">No mobile — WhatsApp cannot send</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
                        <div>
                            @if ($checkedOut !== null)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-200/80 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-gray-700 ring-1 ring-gray-300/50 dark:bg-white/10 dark:text-gray-300 dark:ring-white/10">
                                    <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span>
                                    Left · OUT {{ $checkedOut }}
                                </span>
                            @elseif ($isInside)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-500/25 dark:text-emerald-300">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    </span>
                                    Inside · IN {{ $checkedIn }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                                    Not arrived yet
                                </span>
                            @endif
                        </div>

                        <div class="inline-flex overflow-hidden rounded-2xl bg-gray-100 p-1 shadow-inner ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
                            <button
                                type="button"
                                wire:click="markManualInForStudent({{ $student->id }})"
                                wire:loading.attr="disabled"
                                wire:target="markManualInForStudent({{ $student->id }})"
                                @class([
                                    'min-w-[4.5rem] rounded-xl px-4 py-2.5 text-sm font-extrabold uppercase tracking-wide transition disabled:opacity-60',
                                    'bg-emerald-500 text-white shadow-sm' => $checkedIn !== null,
                                    'text-gray-500 hover:bg-white hover:text-emerald-700 dark:hover:bg-white/10 dark:hover:text-emerald-300' => $checkedIn === null,
                                ])
                                title="Check in — saves now + parent WhatsApp"
                            >
                                <span wire:loading.remove wire:target="markManualInForStudent({{ $student->id }})">IN</span>
                                <span wire:loading wire:target="markManualInForStudent({{ $student->id }})">…</span>
                            </button>
                            <button
                                type="button"
                                wire:click="markManualOutForStudent({{ $student->id }})"
                                wire:loading.attr="disabled"
                                wire:target="markManualOutForStudent({{ $student->id }})"
                                @disabled($checkedIn === null)
                                @class([
                                    'min-w-[4.5rem] rounded-xl px-4 py-2.5 text-sm font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-40',
                                    'bg-rose-500 text-white shadow-sm' => $checkedOut !== null,
                                    'text-gray-500 hover:bg-white hover:text-rose-700 dark:hover:bg-white/10 dark:hover:text-rose-300' => $checkedOut === null,
                                ])
                                title="Check out — saves now + parent WhatsApp"
                            >
                                <span wire:loading.remove wire:target="markManualOutForStudent({{ $student->id }})">OUT</span>
                                <span wire:loading wire:target="markManualOutForStudent({{ $student->id }})">…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
