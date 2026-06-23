@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\BatchStudent> $roster */
    $studentIds = $roster->pluck('student_id')->values()->all();
@endphp

<div
    x-data="{
        marks: @js($marks),
        studentIds: @js($studentIds),
        saving: false,
        isPresent(id) {
            return !!this.marks[id];
        },
        setPresent(id, value) {
            this.marks[id] = value;
        },
        markAllPresent() {
            this.studentIds.forEach((id) => { this.marks[id] = true; });
        },
        async save() {
            this.saving = true;
            try {
                await $wire.saveAttendance(this.marks);
            } finally {
                this.saving = false;
            }
        }
    }"
    class="space-y-4"
>
    @if (! empty($banner))
        <div class="rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-100 dark:ring-sky-500/30">
            {!! $banner !!}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $roster->count() }} student(s) · taps update instantly (no page reload)
        </p>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                x-on:click="markAllPresent()"
                class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
            >
                Mark all Present
            </button>
            <button
                type="button"
                x-on:click="save()"
                x-bind:disabled="saving"
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-60"
            >
                <span x-show="! saving">Save &amp; finish</span>
                <span x-show="saving" x-cloak>Saving…</span>
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($roster as $row)
                @php $student = $row->student; @endphp
                <div class="flex flex-col gap-3 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:bg-gray-900">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $student->name }}</p>
                        @if (filled($student->mobile))
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->mobile }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <button
                            type="button"
                            x-on:click="setPresent({{ $student->id }}, true)"
                            x-bind:class="isPresent({{ $student->id }})
                                ? 'bg-emerald-500 text-white ring-emerald-600'
                                : 'bg-gray-50 text-gray-600 ring-gray-200 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'"
                            class="min-w-[4.5rem] rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide ring-1 transition"
                        >
                            Present
                        </button>
                        <button
                            type="button"
                            x-on:click="setPresent({{ $student->id }}, false)"
                            x-bind:class="! isPresent({{ $student->id }})
                                ? 'bg-rose-500 text-white ring-rose-600'
                                : 'bg-gray-50 text-gray-600 ring-gray-200 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'"
                            class="min-w-[4.5rem] rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide ring-1 transition"
                        >
                            Absent
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end">
        <button
            type="button"
            x-on:click="save()"
            x-bind:disabled="saving"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-60 sm:w-auto"
        >
            <span x-show="! saving">Save attendance &amp; finish</span>
            <span x-show="saving" x-cloak>Saving…</span>
        </button>
    </div>
</div>
