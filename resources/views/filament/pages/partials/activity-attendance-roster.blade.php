@if (! $rosterLoaded)
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Open this page from <span class="font-semibold">Tests &amp; Exams</span>.
    </div>
@elseif ($roster->isEmpty())
    <div class="rounded-xl bg-amber-50 px-4 py-8 text-center text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
        No active students in this batch. Assign students to the batch first.
    </div>
@elseif (! $supportsScoring)
    @php
        $banner = filled($activityTitle)
            ? '<p class="font-semibold">'.e($activityTitle).'</p>'
            : null;
    @endphp

    @include('filament.pages.partials.fast-present-absent-roster', [
        'roster' => $roster,
        'marks' => $marks,
        'banner' => $banner,
    ])
@else
    @php
        $studentIds = $roster->pluck('student_id')->values()->all();
    @endphp

    <div
        x-data="{
            marks: @js($marks),
            studentIds: @js($studentIds),
            saving: false,
            isPresent(id) { return !!this.marks[id]; },
            setPresent(id, value) { this.marks[id] = value; },
            markAllPresent() { this.studentIds.forEach((id) => { this.marks[id] = true; }); },
            async save() {
                this.saving = true;
                try { await $wire.saveAttendance(this.marks); } finally { this.saving = false; }
            }
        }"
        class="space-y-4"
    >
        @if ($activityTitle)
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $activityTitle }}</p>
        @endif
        @if ($maxMarks)
            <p class="text-xs text-gray-500 dark:text-gray-400">Max marks: <span class="font-semibold">{{ $maxMarks }}</span></p>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $roster->count() }} student(s)</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-on:click="markAllPresent()" class="inline-flex rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">
                    Mark all Present
                </button>
                <button type="button" x-on:click="save()" x-bind:disabled="saving" class="inline-flex rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-60">
                    <span x-show="! saving">Save &amp; finish</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($roster as $row)
                    @php $student = $row->student; @endphp
                    <div class="flex flex-col gap-3 bg-white px-4 py-3 dark:bg-gray-900">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $student->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->mobile }}</p>
                            </div>
                            <div class="flex shrink-0 gap-1.5">
                                <button type="button" x-on:click="setPresent({{ $student->id }}, true)" x-bind:class="isPresent({{ $student->id }}) ? 'bg-emerald-500 text-white ring-emerald-600' : 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'" class="min-w-[4.5rem] rounded-lg px-3 py-2 text-xs font-bold uppercase ring-1 transition">Present</button>
                                <button type="button" x-on:click="setPresent({{ $student->id }}, false)" x-bind:class="! isPresent({{ $student->id }}) ? 'bg-rose-500 text-white ring-rose-600' : 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'" class="min-w-[4.5rem] rounded-lg px-3 py-2 text-xs font-bold uppercase ring-1 transition">Absent</button>
                            </div>
                        </div>
                        <div x-show="isPresent({{ $student->id }})" x-cloak class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Marks</label>
                                <input type="number" step="0.01" min="0" @if ($maxMarks) max="{{ $maxMarks }}" @endif wire:model="scoreMarks.{{ $student->id }}.marks_obtained" class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="{{ $maxMarks ? 'out of '.$maxMarks : 'Score' }}">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Grade</label>
                                <input type="text" wire:model="scoreMarks.{{ $student->id }}.grade" class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Remarks</label>
                                <input type="text" wire:model="scoreMarks.{{ $student->id }}.remarks" class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" x-on:click="save()" x-bind:disabled="saving" class="inline-flex w-full justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-60 sm:w-auto">
                <span x-show="! saving">Save marks &amp; finish</span>
                <span x-show="saving" x-cloak>Saving…</span>
            </button>
        </div>
    </div>
@endif
