{{-- Attendance hub: overview + class-wise + unified feed + desk cards --}}
<div class="space-y-5">
    {{-- Date + type filters --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="fi-crm-form grid gap-3 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Date</label>
                <input
                    type="date"
                    wire:model.live="overviewDate"
                    class="fi-crm-input block w-full"
                />
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Show in list</label>
                <x-crm.select wire:model.live="feedType" class="w-full">
                    <option value="all">Students + staff</option>
                    <option value="student">Students only</option>
                    <option value="staff">Staff only</option>
                </x-crm.select>
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            Overview for <strong class="text-gray-950 dark:text-white">{{ $overview['date_label'] }}</strong>
            — includes manual roll call and machine punches.
        </p>
    </div>

    {{-- Totals --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Students present</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                {{ $overview['students_present'] }}
                <span class="text-sm font-medium text-gray-500">/ {{ $overview['students_expected'] }}</span>
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Absent {{ $overview['students_absent'] }} · Leave {{ $overview['students_leave'] }} · Unmarked {{ $overview['students_unmarked'] }}
            </p>
        </div>
        <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Staff present</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                {{ $overview['staff_present'] }}
                <span class="text-sm font-medium text-gray-500">/ {{ $overview['staff_expected'] }}</span>
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Absent {{ $overview['staff_absent'] }} · Leave {{ $overview['staff_leave'] }} · Unmarked {{ $overview['staff_unmarked'] }}
            </p>
        </div>
        <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Students marked</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $overview['students_marked'] }}</p>
            <p class="mt-1 text-xs text-gray-500">P + A + L for this date</p>
        </div>
        <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Staff marked</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $overview['staff_marked'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Teachers + office for this date</p>
        </div>
    </div>

    {{-- Class-wise --}}
    @if (count($overview['class_rows']) > 0)
        <div class="crm-att-hub-classes overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-3 py-2.5 dark:border-white/10 sm:px-4 sm:py-3">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Class-wise (students)</h3>
                <p class="text-xs text-gray-500">Tap Present or Absent to see names. Absent = not present / not on leave.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="crm-att-hub-classes__table min-w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-1.5 sm:px-4">Class</th>
                            <th class="px-2 py-1.5 text-center sm:px-3">Present</th>
                            <th class="px-2 py-1.5 text-center sm:px-3">Absent</th>
                            <th class="px-2 py-1.5 text-center sm:px-3">Leave</th>
                            <th class="px-2 py-1.5 text-center sm:px-3">Expected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($overview['class_rows'] as $row)
                            <tr wire:key="class-{{ $row['batch_id'] }}" class="crm-att-hub-classes__row">
                                <td class="px-3 py-1.5 text-xs font-medium text-gray-950 sm:px-4 sm:text-sm dark:text-white">{{ $row['name'] }}</td>
                                <td class="px-2 py-1.5 text-center sm:px-3">
                                    <button
                                        type="button"
                                        wire:click="openClassDrill({{ $row['batch_id'] }}, 'present')"
                                        class="crm-att-hub-classes__count crm-att-hub-classes__count--present"
                                        @disabled($row['present'] < 1)
                                    >{{ $row['present'] }}</button>
                                </td>
                                <td class="px-2 py-1.5 text-center sm:px-3">
                                    <button
                                        type="button"
                                        wire:click="openClassDrill({{ $row['batch_id'] }}, 'absent')"
                                        class="crm-att-hub-classes__count crm-att-hub-classes__count--absent"
                                        @disabled($row['absent'] < 1)
                                    >{{ $row['absent'] }}</button>
                                </td>
                                <td class="px-2 py-1.5 text-center text-amber-700 sm:px-3 dark:text-amber-300">{{ $row['leave'] }}</td>
                                <td class="px-2 py-1.5 text-center text-gray-500 sm:px-3">{{ $row['expected'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! empty($classDrill))
        <div
            class="crm-att-hub-drill fixed inset-0 z-50 flex items-end justify-center bg-gray-950/50 p-0 sm:items-center sm:p-4"
            wire:key="class-drill-{{ $classDrill['batch_id'] }}-{{ $classDrill['bucket'] }}"
            role="dialog"
            aria-modal="true"
            aria-labelledby="crm-att-hub-drill-title"
        >
            <button type="button" class="absolute inset-0 cursor-default" wire:click="closeClassDrill" aria-label="Close"></button>
            <div class="relative flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl dark:bg-gray-900 sm:rounded-2xl">
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <div class="min-w-0">
                        <p id="crm-att-hub-drill-title" class="text-sm font-bold text-gray-950 dark:text-white">
                            {{ $classDrill['bucket'] === 'present' ? 'Present' : 'Absent' }}
                            · {{ $classDrill['batch_name'] }}
                        </p>
                        <p class="text-xs text-gray-500">{{ $classDrill['date_label'] }} · {{ count($classDrill['students']) }} student{{ count($classDrill['students']) === 1 ? '' : 's' }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeClassDrill"
                        class="rounded-lg px-2 py-1 text-sm font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                    >Close</button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    @if ($classDrill['students'] === [])
                        <p class="px-4 py-10 text-center text-sm text-gray-500">No students in this list.</p>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($classDrill['students'] as $student)
                                <li class="px-4 py-3" wire:key="drill-student-{{ $student['id'] }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $student['name'] }}</p>
                                            <p class="text-xs text-gray-500">
                                                @if ($student['roll'])
                                                    Roll {{ $student['roll'] }} ·
                                                @endif
                                                {{ $student['status_label'] }}
                                            </p>
                                        </div>
                                        @if ($student['can_mark'])
                                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                                <button
                                                    type="button"
                                                    wire:click="markHubAbsent({{ $student['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30"
                                                >Mark absent</button>
                                                <button
                                                    type="button"
                                                    wire:click="startLeaveMark({{ $student['id'] }})"
                                                    class="rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30"
                                                >Mark leave</button>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($leaveStudentId === $student['id'])
                                        <div class="mt-3 space-y-2 rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                                            <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Leave reason</label>
                                            <select wire:model="leaveReasonTag" class="fi-crm-input block w-full text-sm">
                                                @foreach ($leaveTags as $tag)
                                                    <option value="{{ $tag }}">{{ $tag }}</option>
                                                @endforeach
                                            </select>
                                            <input
                                                type="text"
                                                wire:model="leaveReasonCustom"
                                                placeholder="Optional note"
                                                class="fi-crm-input block w-full text-sm"
                                            />
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    wire:click="confirmHubLeave"
                                                    class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500"
                                                >Save leave</button>
                                                <button
                                                    type="button"
                                                    wire:click="cancelLeaveMark"
                                                    class="rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-white/10"
                                                >Cancel</button>
                                            </div>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Unified feed --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10">
            <h3 class="text-sm font-bold text-gray-950 dark:text-white">Attendance log</h3>
            <p class="text-xs text-gray-500">Students and staff together · Manual vs auto · WhatsApp on student punches</p>
        </div>

        @if ($feed->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-base font-semibold text-gray-950 dark:text-white">No attendance rows for this date</p>
                <p class="mt-1 text-sm text-gray-500">Pick another date, or mark via live punches / manual batch / staff desk below.</p>
            </div>
        @else
            <div class="hidden grid-cols-12 gap-2 border-b border-gray-100 bg-gray-50 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400 sm:grid">
                <div class="col-span-2">Type</div>
                <div class="col-span-3">Name</div>
                <div class="col-span-2">Status</div>
                <div class="col-span-2">Channel</div>
                <div class="col-span-1">In</div>
                <div class="col-span-1">Out</div>
                <div class="col-span-1">WhatsApp</div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($feed as $row)
                    <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-12 sm:items-center sm:gap-2" wire:key="feed-{{ $row['kind'] }}-{{ $loop->index }}-{{ $row['name'] }}">
                        <div class="sm:col-span-2">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold text-white',
                                'bg-amber-500' => $row['kind'] === 'student',
                                'bg-sky-600' => $row['kind'] === 'staff',
                            ])>{{ $row['kind_label'] }}</span>
                        </div>
                        <div class="sm:col-span-3">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                            <p class="text-xs text-gray-500">{{ $row['detail'] }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <span @class([
                                'text-sm font-semibold',
                                'text-emerald-700 dark:text-emerald-300' => ($row['status_value'] ?? '') === 'present',
                                'text-rose-700 dark:text-rose-300' => ($row['status_value'] ?? '') === 'absent',
                                'text-amber-700 dark:text-amber-300' => ($row['status_value'] ?? '') === 'leave',
                            ])>{{ $row['status'] }}</span>
                            <p class="text-[11px] text-gray-500 sm:hidden">{{ $row['source'] }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-sm text-gray-800 dark:text-gray-200">{{ $row['channel'] }}</p>
                            <p class="hidden text-[11px] text-gray-500 sm:block">{{ $row['source'] }}</p>
                        </div>
                        <div class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-1">{{ $row['in_at'] ?? '—' }}</div>
                        <div class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-1">{{ $row['out_at'] ?? '—' }}</div>
                        <div class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-1">{{ $row['whatsapp'] }}</div>
                    </div>
                @endforeach
            </div>
            <div class="border-t border-gray-100 px-4 py-3 dark:border-white/10">
                {{ $feed->links() }}
            </div>
        @endif
    </div>

    {{-- Desk cards (existing tools) --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-col gap-1">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">Attendance desk</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300">Mark students and staff from here. Same ADMS machine and Face app — the PIN decides who is who.</p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <a
                href="{{ $card['url'] }}"
                @class([
                    'group rounded-2xl border p-5 transition hover:-translate-y-0.5 hover:shadow-md',
                    'border-primary-200 bg-primary-50 hover:border-primary-300 dark:border-primary-500/30 dark:bg-primary-500/10' => ($card['tone'] ?? null) === 'primary',
                    'border-gray-200 bg-white hover:border-amber-300 dark:border-white/10 dark:bg-gray-900' => ($card['tone'] ?? null) !== 'primary',
                ])
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        @if (! empty($card['badge']))
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-1 text-xs font-bold text-white',
                                'bg-primary-600' => ($card['tone'] ?? null) === 'primary',
                                'bg-amber-500' => ($card['tone'] ?? null) !== 'primary',
                            ])>{{ $card['badge'] }}</span>
                        @endif
                        <h3 @class([
                            'text-base font-bold text-gray-950 dark:text-white',
                            'mt-3' => ! empty($card['badge']),
                        ])>{{ $card['title'] }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $card['description'] }}</p>
                    </div>
                    <span @class([
                        'text-xl transition group-hover:translate-x-1',
                        'text-primary-600 dark:text-primary-400' => ($card['tone'] ?? null) === 'primary',
                        'text-amber-600 dark:text-amber-400' => ($card['tone'] ?? null) !== 'primary',
                    ])>→</span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
        <strong class="text-gray-900 dark:text-white">Setup:</strong> Student Roll No. and Staff ID must be unique. Put the same code on the ADMS machine and Face app. Missing Staff IDs → Admin → Staff → Assign missing Staff IDs, then Sync to Face API. Parent WhatsApp on punch still follows WhatsApp → Automations.
    </div>
</div>
