<div class="mt-4 space-y-4">
    @if ($confirmNotDoneOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4">
            <div
                class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                wire:key="hw-confirm-not-done"
            >
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Send WhatsApp for Not Done?</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    You selected <strong>{{ $selectedCount }}</strong> student(s) for
                    <strong>{{ $subjectLabel }}</strong> on <strong>{{ $checkDateLabel }}</strong>.
                </p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-200">
                    <li>
                        WhatsApp will be attempted for
                        <strong>{{ $selectedWithMobile }}</strong> student(s) who have a mobile number.
                    </li>
                    @if ($selectedWithoutMobile > 0)
                        <li>
                            <strong>{{ $selectedWithoutMobile }}</strong> have no mobile — they will be marked Not Done
                            but WhatsApp cannot be sent.
                        </li>
                    @endif
                    <li>Done students are not messaged. Only this Not Done submit sends WhatsApp.</li>
                </ul>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelMarkSelectedNotDone"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="confirmMarkSelectedNotDone"
                        wire:loading.attr="disabled"
                        wire:target="confirmMarkSelectedNotDone"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-500 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="confirmMarkSelectedNotDone">
                            Yes, mark Not Done &amp; send
                        </span>
                        <span wire:loading wire:target="confirmMarkSelectedNotDone">Sending…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if (! $rosterReady)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            Select a <strong>class</strong>. Subject will auto-fill if you teach only one; otherwise pick the subject, then the student list opens.
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
                        Tick students who did <strong>not</strong> finish, then Submit. The system will ask before sending WhatsApp.
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
                        wire:click="requestMarkSelectedNotDone"
                        wire:loading.attr="disabled"
                        wire:target="requestMarkSelectedNotDone,confirmMarkSelectedNotDone,markRemainingDone"
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-rose-500 disabled:opacity-50"
                    >
                        Submit Not Done
                        @if (count($selectedStudentIds) > 0)
                            ({{ count($selectedStudentIds) }})
                        @endif
                    </button>
                    <button
                        type="button"
                        wire:click="markRemainingDone"
                        wire:loading.attr="disabled"
                        wire:target="requestMarkSelectedNotDone,confirmMarkSelectedNotDone,markRemainingDone"
                        @disabled($unmarkedCount < 1)
                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-500 disabled:opacity-50"
                    >
                        Mark remaining Done{{ $unmarkedCount > 0 ? ' ('.$unmarkedCount.')' : '' }}
                    </button>
                </div>
            </div>

            <x-crm.responsive-table class="border-t border-gray-100 dark:border-gray-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 w-10"></th>
                            <th class="px-4 py-2">Student</th>
                            <th class="px-4 py-2">Mobile</th>
                            <th class="px-4 py-2">Week ND</th>
                            <th class="px-4 py-2">{{ $checkDateLabel }}</th>
                            <th class="px-4 py-2 text-right">Quick</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($students as $student)
                            <tr wire:key="hw-roster-{{ $student['id'] }}">
                                <td class="px-4 py-2 crm-responsive-table__title" data-label="">
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            value="{{ $student['id'] }}"
                                            wire:model.live="selectedStudentIds"
                                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                        />
                                        <span class="font-medium text-gray-950 md:hidden dark:text-white">{{ $student['name'] }}</span>
                                    </label>
                                </td>
                                <td class="hidden px-4 py-2 font-medium md:table-cell" data-label="Student">{{ $student['name'] }}</td>
                                <td class="px-4 py-2 text-gray-500" data-label="Mobile">{{ $student['mobile'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-xs" data-label="Week ND">
                                    @if (($student['not_done_week'] ?? 0) > 0)
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                            {{ $student['not_done_week'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500" data-label="{{ $checkDateLabel }}">
                                    @if ($student['last_status'])
                                        {{ $student['last_status'] }}
                                        @if ($student['last_notify'])
                                            · {{ $student['last_notify'] }}
                                        @endif
                                        @if ($student['can_resend'] && $student['check_id'])
                                            <button
                                                type="button"
                                                wire:click="resendWhatsApp({{ $student['check_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="resendWhatsApp({{ $student['check_id'] }})"
                                                class="ml-1 font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                Resend
                                            </button>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right crm-responsive-table__actions" data-label="">
                                    <button
                                        type="button"
                                        wire:click="markStudentDone({{ $student['id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="markStudentDone({{ $student['id'] }})"
                                        class="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-extrabold uppercase text-white disabled:opacity-50 touch-manipulation min-h-10"
                                    >
                                        Done
                                    </button>
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

    @if ($recent->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold dark:border-gray-800">
                Marks for {{ $checkDateLabel }}
            </div>
            <x-crm.responsive-table class="border-t border-gray-100 dark:border-gray-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2">Student</th>
                            <th class="px-4 py-2">Subject</th>
                            <th class="px-4 py-2">Topic</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">WhatsApp</th>
                            <th class="px-4 py-2">Time</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($recent as $row)
                            @php
                                $canResend = $row->status === \App\Enums\HomeworkCheckStatus::NotDone
                                    && in_array($row->notify_status, [
                                        \App\Enums\HomeworkCheckNotifyStatus::Failed,
                                        \App\Enums\HomeworkCheckNotifyStatus::Pending,
                                    ], true);
                            @endphp
                            <tr wire:key="hw-check-{{ $row->id }}">
                                <td class="px-4 py-2 font-medium crm-responsive-table__title" data-label="Student">{{ $row->student?->name }}</td>
                                <td class="px-4 py-2" data-label="Subject">{{ $row->subject_name }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300" data-label="Topic">{{ \Illuminate\Support\Str::limit($row->topic, 40) }}</td>
                                <td class="px-4 py-2" data-label="Status">{{ $row->status?->label() }}</td>
                                <td class="px-4 py-2 text-gray-500" data-label="WhatsApp">{{ $row->notify_status?->label() }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500" data-label="Time">{{ $row->created_at?->format('d M H:i') }}</td>
                                <td class="px-4 py-2 text-right crm-responsive-table__actions" data-label="">
                                    @if ($canResend)
                                        <button
                                            type="button"
                                            wire:click="resendWhatsApp({{ $row->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="resendWhatsApp({{ $row->id }})"
                                            class="min-h-10 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400 touch-manipulation"
                                        >
                                            Resend
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
        </div>
    @endif
</div>
