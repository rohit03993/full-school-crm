<div class="mt-4 space-y-4">
    @if (! $rosterReady)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            Select a <strong>class</strong>. Subject will auto-fill if you teach only one; otherwise pick the subject, then the student list opens.
        </div>
    @elseif (! ($homeworkGiven ?? false))
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            No homework was given for <strong>{{ $subjectLabel }}</strong> on <strong>{{ $checkDateLabel }}</strong>. Give homework first, then mark Done or Not done.
        </div>
    @else
        @if (count($otherSubjectsToday) > 1)
            <div class="flex flex-wrap gap-2">
                @foreach ($otherSubjectsToday as $subjectRow)
                    <div @class([
                        'rounded-xl border px-3 py-2 text-xs',
                        'border-primary-300 bg-primary-50 dark:border-primary-500/40 dark:bg-primary-500/10' => $subjectRow['is_active'],
                        'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' => ! $subjectRow['is_active'],
                    ])>
                        <p class="font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">{{ $subjectRow['label'] }}</p>
                        <p class="mt-1 text-gray-500">
                            Done {{ $subjectRow['done'] }} · ND {{ $subjectRow['not_done'] }} · Open {{ $subjectRow['unmarked'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Done % · {{ $subjectLabel }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-900 dark:text-emerald-200">{{ $summary['done_pct'] }}%</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Done</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary['done'] }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-500/20 dark:bg-rose-500/10">
                <p class="text-[11px] font-bold uppercase tracking-wide text-rose-700 dark:text-rose-300">Not Done</p>
                <p class="mt-1 text-2xl font-bold text-rose-900 dark:text-rose-200">{{ $summary['not_done'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Unmarked</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary['unmarked'] }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $subjectLabel }} · {{ $checkDateLabel }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Tap <strong>Done</strong> or <strong>Not done</strong> on a student. Not done shares a message with parents. Done only changes the status.
                    </p>
                    @php
                        $linkTrackedStudents = collect($students)->where('link_tracked', true);
                        $linkOpenedCount = $linkTrackedStudents->where('link_opened', true)->count();
                    @endphp
                    @if ($linkTrackedStudents->isNotEmpty())
                        <p class="mt-1 text-xs font-semibold text-gray-700 dark:text-gray-200">
                            Link opened {{ $linkOpenedCount }} / {{ $linkTrackedStudents->count() }}
                        </p>
                    @endif
                </div>
            </div>

            <x-crm.responsive-table class="border-t border-gray-100 dark:border-gray-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2">Student</th>
                            @if ($showStudentMobile ?? false)
                                <th class="px-4 py-2">Mobile</th>
                            @endif
                            <th class="px-4 py-2">Link</th>
                            <th class="px-4 py-2">Week ND</th>
                            <th class="px-4 py-2">{{ $checkDateLabel }}</th>
                            <th class="px-4 py-2 text-right">Quick</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($students as $student)
                            <tr wire:key="hw-roster-{{ $student['id'] }}">
                                <td class="px-4 py-2 font-medium crm-responsive-table__title" data-label="Student">
                                    <x-crm.person-name :student-id="$student['id']" :name="$student['name']" />
                                </td>
                                @if ($showStudentMobile ?? false)
                                    <td class="px-4 py-2 text-gray-500" data-label="Mobile">{{ $student['mobile'] ?: '—' }}</td>
                                @endif
                                <td class="px-4 py-2 text-xs" data-label="Link">
                                    @if ($student['link_tracked'] ?? false)
                                        @if ($student['link_opened'] ?? false)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">
                                                Opened
                                                @if (filled($student['link_opened_at'] ?? null))
                                                    · {{ $student['link_opened_at'] }}
                                                @endif
                                            </span>
                                        @else
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                                Not opened
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs" data-label="Week ND">
                                    @if (($student['not_done_week'] ?? 0) > 0)
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                            {{ $student['not_done_week'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="crm-responsive-table__wide px-4 py-2 text-xs text-gray-500" data-label="{{ $checkDateLabel }}">
                                    @if ($student['last_status'])
                                        {{ $student['last_status'] }}
                                        @if (filled($student['parent_line'] ?? null))
                                            · {{ $student['parent_line'] }}
                                        @endif
                                        @if ($student['can_resend'] && $student['check_id'])
                                            <button
                                                type="button"
                                                wire:click="resendWhatsApp({{ $student['check_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="resendWhatsApp({{ $student['check_id'] }})"
                                                class="ml-1 font-semibold text-primary-600 hover:underline dark:text-primary-400 disabled:cursor-wait disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="resendWhatsApp({{ $student['check_id'] }})">Resend</span>
                                                <span wire:loading wire:target="resendWhatsApp({{ $student['check_id'] }})">Queuing…</span>
                                            </button>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right crm-responsive-table__actions" data-label="">
                                    <div class="inline-flex overflow-hidden rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                                        <button
                                            type="button"
                                            wire:click="markStudentDone({{ $student['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="markStudentDone({{ $student['id'] }}),markStudentNotDone({{ $student['id'] }})"
                                            @class([
                                                'min-h-10 px-3 py-1.5 text-xs font-extrabold uppercase touch-manipulation',
                                                'bg-emerald-600 text-white' => ($student['status_key'] ?? null) === 'done',
                                                'bg-white text-gray-500 hover:bg-emerald-50 dark:bg-gray-900 dark:text-gray-300' => ($student['status_key'] ?? null) !== 'done',
                                            ])
                                        >
                                            Done
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="markStudentNotDone({{ $student['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="markStudentDone({{ $student['id'] }}),markStudentNotDone({{ $student['id'] }})"
                                            @class([
                                                'min-h-10 px-3 py-1.5 text-xs font-extrabold uppercase touch-manipulation',
                                                'bg-rose-600 text-white' => ($student['status_key'] ?? null) === 'not_done',
                                                'bg-white text-gray-500 hover:bg-rose-50 dark:bg-gray-900 dark:text-gray-300' => ($student['status_key'] ?? null) !== 'not_done',
                                            ])
                                        >
                                            Not done
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No students found for this class.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-crm.responsive-table>
        </div>
    @endif
</div>
