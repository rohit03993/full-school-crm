<div class="mt-4 space-y-4">
    @if (! $rosterReady)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            Select a <strong>class</strong>, then a <strong>subject</strong> to open the student list.
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Students</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Done = save only. Not Done = save + WhatsApp (subject included).
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="toggleSelectAll"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                    >
                        {{ count($selectedStudentIds) === count($students) && count($students) > 0 ? 'Clear selection' : 'Select all' }}
                    </button>
                    <button
                        type="button"
                        wire:click="markSelectedNotDone"
                        wire:loading.attr="disabled"
                        wire:target="markSelectedNotDone,markStudentDone,markStudentNotDone"
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-rose-500 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="markSelectedNotDone">Mark Not Done</span>
                        <span wire:loading wire:target="markSelectedNotDone">Sending…</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 w-10"></th>
                            <th class="px-4 py-2">Student</th>
                            <th class="px-4 py-2">Mobile</th>
                            <th class="px-4 py-2">Today</th>
                            <th class="px-4 py-2 text-right">Mark</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($students as $student)
                            <tr wire:key="hw-roster-{{ $student['id'] }}">
                                <td class="px-4 py-2">
                                    <input
                                        type="checkbox"
                                        value="{{ $student['id'] }}"
                                        wire:model.live="selectedStudentIds"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                    />
                                </td>
                                <td class="px-4 py-2 font-medium">{{ $student['name'] }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $student['mobile'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    @if ($student['last_status'])
                                        {{ $student['last_status'] }}
                                        @if ($student['last_notify'])
                                            · {{ $student['last_notify'] }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex justify-end gap-1">
                                        <button
                                            type="button"
                                            wire:click="markStudentDone({{ $student['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="markStudentDone({{ $student['id'] }}),markStudentNotDone({{ $student['id'] }}),markSelectedNotDone"
                                            class="min-w-[3.5rem] rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-extrabold uppercase text-white disabled:opacity-50"
                                        >
                                            Done
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="markStudentNotDone({{ $student['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="markStudentDone({{ $student['id'] }}),markStudentNotDone({{ $student['id'] }}),markSelectedNotDone"
                                            class="min-w-[4.5rem] rounded-md bg-rose-600 px-2.5 py-1.5 text-xs font-extrabold uppercase text-white disabled:opacity-50"
                                        >
                                            Not Done
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No students found for this class.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold dark:border-gray-800">Recent marks (this class)</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2">Student</th>
                            <th class="px-4 py-2">Subject</th>
                            <th class="px-4 py-2">Topic</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">WhatsApp</th>
                            <th class="px-4 py-2">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($recent as $row)
                            <tr wire:key="hw-check-{{ $row->id }}">
                                <td class="px-4 py-2 font-medium">{{ $row->student?->name }}</td>
                                <td class="px-4 py-2">{{ $row->subject_name }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($row->topic, 40) }}</td>
                                <td class="px-4 py-2">{{ $row->status?->label() }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $row->notify_status?->label() }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $row->created_at?->format('d M H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
