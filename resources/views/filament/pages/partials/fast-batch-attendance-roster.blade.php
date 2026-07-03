@php
    use App\Enums\AttendanceStatus;

    /** @var \Illuminate\Support\Collection<int, \App\Models\BatchStudent> $roster */
    $studentIds = $roster->pluck('student_id')->values()->all();
@endphp

<div
    x-data="{
        marks: $wire.entangle('marks'),
        studentIds: @js($studentIds),
        saving: false,
        punchingIn: null,
        current(id) { return this.marks[id] ?? 'absent'; },
        setStatus(id, value) { this.marks[id] = value; },
        markAllIn() { this.studentIds.forEach((id) => { this.marks[id] = 'present'; }); },
        async save() {
            this.saving = true;
            try { await $wire.saveAttendance(this.marks); } finally { this.saving = false; }
        }
    }"
    wire:key="manual-attendance-roster"
    class="space-y-4"
>
    <div class="fi-section rounded-2xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
        <p class="text-sm font-bold text-gray-950 dark:text-white">Same flow as live punches</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Tap <strong class="text-emerald-600">IN</strong> to check in immediately (saves + parent WhatsApp) ·
            <strong class="text-rose-600">OUT</strong> checks out now ·
            <strong>A / L</strong> use <strong>Save IN / A / L</strong> (no punch message)
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" x-on:click="markAllIn()" class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-bold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200">
                All IN (select only)
            </button>
            <button type="button" x-on:click="save()" x-bind:disabled="saving" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-60">
                <span x-show="! saving">Save IN / A / L</span>
                <span x-show="saving" x-cloak>Saving…</span>
            </button>
        </div>
    </div>

    <div class="fi-section overflow-hidden rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($roster as $row)
                @php $student = $row->student; @endphp
                <div
                    wire:key="manual-student-{{ $student->id }}"
                    class="flex flex-col gap-3 bg-white px-4 py-4 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-500/10 text-sm font-bold text-primary-700 dark:text-primary-300">
                            {{ strtoupper(substr($student->name, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-bold text-gray-950 dark:text-white">{{ $student->name }}</p>
                            @if (filled($student->mobile))
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->mobile }}</p>
                            @else
                                <p class="text-xs text-amber-600 dark:text-amber-400">No mobile — WhatsApp cannot send</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <div class="flex gap-1 rounded-xl bg-gray-50 p-1 dark:bg-white/5">
                            <button
                                type="button"
                                wire:click="markManualInForStudent({{ $student->id }})"
                                wire:loading.attr="disabled"
                                wire:target="markManualInForStudent({{ $student->id }})"
                                x-bind:class="current({{ $student->id }}) === 'present' ? 'bg-emerald-500 text-white shadow-sm' : 'text-gray-500 hover:bg-white dark:hover:bg-white/10'"
                                class="rounded-lg px-3 py-2 text-xs font-extrabold uppercase tracking-wide transition disabled:opacity-60"
                                title="Check in now — saves + parent WhatsApp"
                            >
                                <span wire:loading.remove wire:target="markManualInForStudent({{ $student->id }})">IN</span>
                                <span wire:loading wire:target="markManualInForStudent({{ $student->id }})">…</span>
                            </button>
                            @foreach ([AttendanceStatus::Absent, AttendanceStatus::Leave] as $status)
                                <button
                                    type="button"
                                    x-on:click="setStatus({{ $student->id }}, '{{ $status->value }}')"
                                    x-bind:class="current({{ $student->id }}) === '{{ $status->value }}'
                                        ? @js(match ($status) {
                                            AttendanceStatus::Absent => 'bg-rose-500 text-white shadow-sm',
                                            AttendanceStatus::Leave => 'bg-amber-400 text-amber-950 shadow-sm',
                                        })
                                        : 'text-gray-500 hover:bg-white dark:hover:bg-white/10'"
                                    class="rounded-lg px-3 py-2 text-xs font-extrabold uppercase tracking-wide transition"
                                >
                                    {{ $status->code() }}
                                </button>
                            @endforeach
                        </div>
                        <button
                            type="button"
                            wire:click="markManualOutForStudent({{ $student->id }})"
                            wire:loading.attr="disabled"
                            wire:target="markManualOutForStudent({{ $student->id }})"
                            class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-bold text-white hover:bg-rose-500 disabled:opacity-60"
                            title="Check out now — saves + parent WhatsApp"
                        >
                            <span wire:loading.remove wire:target="markManualOutForStudent({{ $student->id }})">OUT</span>
                            <span wire:loading wire:target="markManualOutForStudent({{ $student->id }})">…</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
