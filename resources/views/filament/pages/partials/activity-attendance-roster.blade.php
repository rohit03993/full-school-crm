@if (! $rosterLoaded)
    <div class="mx-auto max-w-4xl rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Open this page from <span class="font-semibold">Tests &amp; Exams</span> or an exam window.
    </div>
@elseif ($roster->isEmpty())
    <div class="mx-auto max-w-4xl rounded-xl bg-amber-50 px-4 py-8 text-center text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
        No active students in this batch. Assign students to the batch first.
    </div>
@elseif (! $supportsScoring)
    @php
        $banner = filled($examName ?? null)
            ? '<p class="font-semibold">'.e($examName).'</p>'
            : null;
    @endphp

    <div class="mx-auto max-w-4xl">
        @include('filament.pages.partials.fast-present-absent-roster', [
            'roster' => $roster,
            'marks' => $marks,
            'banner' => $banner,
        ])
    </div>
@else
    @php
        $studentIds = $roster->pluck('student_id')->values()->all();
        $totalStudents = $roster->count();
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
        class="mx-auto max-w-5xl space-y-4 pb-28 lg:pb-8"
    >
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    @if (! empty($examWindowBackUrl))
                        <a
                            href="{{ $examWindowBackUrl }}"
                            class="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-3.5 w-3.5" />
                            Back to exam window
                        </a>
                    @endif
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Mark entry</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Mark <strong class="text-gray-900 dark:text-white">Present</strong> or <strong class="text-gray-900 dark:text-white">Absent</strong>, then enter marks for students who appeared.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5 text-center dark:bg-white/5">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Students</p>
                        <p class="mt-0.5 text-xl font-bold text-gray-950 dark:text-white">{{ $totalStudents }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5 text-center dark:bg-white/5">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Max marks</p>
                        <p class="mt-0.5 text-xl font-bold text-gray-950 dark:text-white">{{ $maxMarks ? (int) $maxMarks : '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 px-3 py-2.5 text-center dark:bg-emerald-500/10">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Entered</p>
                        <p class="mt-0.5 text-xl font-bold text-emerald-800 dark:text-emerald-200">{{ $enteredMarksCount }}/{{ $totalStudents }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="hidden overflow-hidden rounded-2xl bg-gray-50/80 px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10 lg:grid lg:grid-cols-[minmax(0,1.4fr)_9rem_6rem_5rem_minmax(0,1fr)] lg:gap-3 lg:px-5">
            <span>Student</span>
            <span class="text-center">Status</span>
            <span class="text-center">Marks</span>
            <span class="text-center">Grade</span>
            <span>Remarks</span>
        </div>

        <div class="overflow-hidden rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($roster as $row)
                    @php $student = $row->student; @endphp
                    <div
                        wire:key="marks-row-{{ $student->id }}"
                        class="bg-white px-4 py-4 dark:bg-gray-900 sm:px-5"
                    >
                        <div class="flex flex-col gap-4 lg:grid lg:grid-cols-[minmax(0,1.4fr)_9rem_6rem_5rem_minmax(0,1fr)] lg:items-center lg:gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-500/10 text-sm font-bold text-primary-700 dark:text-primary-300">
                                    {{ strtoupper(substr($student->name, 0, 1)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $student->name }}</p>
                                    @if (filled($student->mobile))
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->mobile }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex gap-1.5 lg:justify-center">
                                <button
                                    type="button"
                                    x-on:click="setPresent({{ $student->id }}, true)"
                                    x-bind:class="isPresent({{ $student->id }}) ? 'bg-emerald-500 text-white ring-emerald-600' : 'bg-gray-50 text-gray-600 ring-gray-200 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'"
                                    class="min-w-[4.25rem] rounded-lg px-2.5 py-2 text-[11px] font-bold uppercase tracking-wide ring-1 transition"
                                >
                                    Present
                                </button>
                                <button
                                    type="button"
                                    x-on:click="setPresent({{ $student->id }}, false)"
                                    x-bind:class="! isPresent({{ $student->id }}) ? 'bg-rose-500 text-white ring-rose-600' : 'bg-gray-50 text-gray-600 ring-gray-200 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'"
                                    class="min-w-[4.25rem] rounded-lg px-2.5 py-2 text-[11px] font-bold uppercase tracking-wide ring-1 transition"
                                >
                                    Absent
                                </button>
                            </div>

                            <div x-show="isPresent({{ $student->id }})" x-cloak class="lg:contents">
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-gray-500 lg:sr-only dark:text-gray-400">Marks</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        @if ($maxMarks) max="{{ $maxMarks }}" @endif
                                        wire:model="scoreMarks.{{ $student->id }}.marks_obtained"
                                        class="fi-crm-input block w-full text-center text-base font-bold tabular-nums lg:py-2"
                                        placeholder="{{ $maxMarks ? (int) $maxMarks : '0' }}"
                                        inputmode="decimal"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-gray-500 lg:sr-only dark:text-gray-400">Grade</label>
                                    <input
                                        type="text"
                                        wire:model="scoreMarks.{{ $student->id }}.grade"
                                        class="fi-crm-input block w-full text-center lg:py-2"
                                        placeholder="—"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-gray-500 lg:sr-only dark:text-gray-400">Remarks</label>
                                    <input
                                        type="text"
                                        wire:model="scoreMarks.{{ $student->id }}.remarks"
                                        class="fi-crm-input block w-full lg:py-2"
                                        placeholder="Optional"
                                    >
                                </div>
                            </div>

                            <div
                                x-show="! isPresent({{ $student->id }})"
                                x-cloak
                                class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700 ring-1 ring-rose-100 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-500/20 lg:col-span-3"
                            >
                                Marked absent — no marks needed.
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-gray-900/95 lg:static lg:rounded-2xl lg:border-0 lg:px-0 lg:py-0 lg:backdrop-blur-none">
            <div class="mx-auto flex max-w-5xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button
                    type="button"
                    x-on:click="markAllPresent()"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800 ring-1 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
                >
                    <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                    Mark all present
                </button>
                <button
                    type="button"
                    x-on:click="save()"
                    x-bind:disabled="saving"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-60"
                >
                    <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                    <span x-show="! saving">Save marks &amp; finish</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </div>
    </div>
@endif
