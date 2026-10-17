<div class="crm-hw-shell mt-4 space-y-4">
    @if ($showAllClassesLink ?? false)
        <button
            type="button"
            wire:click="showAllClasses"
            class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400"
        >
            All classes
        </button>
    @endif
    <style>
        @media (max-width: 1023px) {
            .crm-hw-topic { display: none !important; }
            .crm-hw-empty { padding: 14px 12px !important; }
            .crm-hw-stats { gap: 6px; }
            .crm-hw-stats > div { padding: 8px 6px; }
            .crm-hw-stats > div p:first-child {
                font-size: 9px;
                letter-spacing: 0;
            }
            .crm-hw-stats > div p:last-child {
                margin-top: 2px;
                font-size: 16px;
                line-height: 1.1;
            }
        }
    </style>
    @if (! $rosterReady)
        <div class="crm-hw-empty rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            Pick a class. The student list opens here.
        </div>
    @elseif (filled($checkDateBlocked ?? null))
        <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-8 text-center text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            {{ $checkDateBlocked }}
        </div>
    @elseif ($homeworkAwaitingApproval ?? false)
        <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-8 text-center text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            Homework for <strong>{{ $subjectLabel }}</strong> on <strong>{{ $checkDateLabel }}</strong> is waiting for admin approval. Student marks open after it is approved.
        </div>
    @elseif (! ($homeworkGiven ?? false))
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            No homework was given for <strong>{{ $subjectLabel }}</strong> on <strong>{{ $checkDateLabel }}</strong>. Give homework first. Done and Not done open after admin approves.
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
                            Done {{ $subjectRow['done'] }} · Not done {{ $subjectRow['not_done'] }} · Not checked {{ $subjectRow['unmarked'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="crm-hw-stats grid grid-cols-4 gap-2 sm:gap-3">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Done %</p>
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
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Not checked</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary['unmarked'] }}</p>
            </div>
        </div>

        <style>
            .crm-hw-tick {
                box-sizing: border-box !important;
                width: 16px !important;
                height: 16px !important;
                min-width: 16px !important;
                max-width: 16px !important;
                min-height: 16px !important;
                max-height: 16px !important;
                padding: 0 !important;
                margin: 0 !important;
                flex: 0 0 16px !important;
                border-radius: 4px !important;
                font-size: 10px !important;
                line-height: 1 !important;
            }
            .crm-hw-name {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .crm-hw-phone-row {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 8px 12px;
                border-top: 1px solid #f3f4f6;
            }
            .crm-hw-phone-row > .crm-hw-tick {
                margin-top: 4px !important;
            }
            .crm-hw-phone-main {
                min-width: 0;
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .crm-hw-phone-head {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 8px;
            }
            .crm-hw-phone-name {
                min-width: 0;
                flex: 1;
                overflow: visible;
                text-overflow: unset;
                white-space: normal;
                overflow-wrap: anywhere;
                font-size: 15px;
                font-weight: 700 !important;
                line-height: 1.35;
                color: #c2410c !important;
            }
            .dark .crm-hw-phone-name {
                color: #fdba74 !important;
            }
            .crm-hw-phone-opened {
                flex: 0 0 auto;
                font-size: 11px;
                line-height: 1.2;
                font-weight: 600;
                color: #047857;
            }
            .crm-hw-phone-actions {
                display: flex;
                width: 100%;
                overflow: hidden;
                border-radius: 8px;
                border: 1px solid #e5e7eb;
            }
            .crm-hw-phone-actions button {
                flex: 1 1 0;
                height: 36px !important;
                min-height: 36px !important;
                min-width: 0 !important;
                width: auto !important;
                padding: 0 4px !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                line-height: 1.1 !important;
                white-space: nowrap;
                border: 0 !important;
            }
            .crm-hw-phone-actions button + button {
                box-shadow: inset 1px 0 0 #ffffff;
            }
            .crm-hw-btn-wait {
                background: #e5e7eb !important;
                color: #1f2937 !important;
            }
            .crm-hw-btn-wait.is-on {
                background: #374151 !important;
                color: #ffffff !important;
            }
            .crm-hw-btn-done {
                background: #d1fae5 !important;
                color: #065f46 !important;
            }
            .crm-hw-btn-done.is-on {
                background: #059669 !important;
                color: #ffffff !important;
            }
            .crm-hw-btn-miss {
                background: #ffe4e6 !important;
                color: #9f1239 !important;
            }
            .crm-hw-btn-miss.is-on {
                background: #e11d48 !important;
                color: #ffffff !important;
            }
            .crm-hw-btn-wait.is-locked,
            .crm-hw-btn-done.is-locked {
                background: #f3f4f6 !important;
                color: #9ca3af !important;
            }
            .crm-hw-phone-meta {
                margin: 0;
                font-size: 11px;
                line-height: 1.2;
                font-weight: 600;
                color: #be123c;
            }
            @media (max-width: 1023px) {
                .crm-hw-shell .grid.gap-3 {
                    gap: 8px;
                }
                .crm-hw-shell .grid.gap-3 > div {
                    padding: 8px 10px;
                }
                .crm-hw-shell .grid.gap-3 > div p:last-child {
                    margin-top: 0;
                    font-size: 18px;
                    line-height: 1.2;
                }
                .crm-hw-head {
                    gap: 8px;
                    padding: 8px 10px;
                }
            }
            .crm-hw-roster-desktop {
                display: none;
            }
            @media (min-width: 1024px) {
                .crm-hw-phone {
                    display: none;
                }
                .crm-hw-roster-desktop {
                    display: block;
                }
            }
        </style>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="crm-hw-head flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $subjectLabel }} · {{ $checkDateLabel }}
                    </p>
                    <p class="hidden text-xs text-gray-500 dark:text-gray-400 lg:block">
                        Tick students. The bar stays on screen while you scroll.
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
                <button
                    type="button"
                    wire:click="toggleSelectAll"
                    class="h-8 shrink-0 rounded-lg bg-gray-100 px-2.5 text-xs font-semibold text-gray-800 dark:bg-white/10 dark:text-gray-100"
                >
                    Select all
                </button>
            </div>

            <div class="crm-hw-phone">
                @forelse ($students as $student)
                    @php $ticked = in_array((int) $student['id'], $selectedStudentIds ?? [], true); @endphp
                    <div class="crm-hw-phone-row" wire:key="hw-phone-{{ $student['id'] }}">
                        <button
                            type="button"
                            wire:click="toggleStudent({{ (int) $student['id'] }})"
                            aria-label="Tick {{ $student['name'] }}"
                            @class([
                                'crm-hw-tick inline-flex items-center justify-center border font-bold',
                                'border-primary-600 bg-primary-600 text-white' => $ticked,
                                'border-gray-300 bg-white text-transparent' => ! $ticked,
                            ])
                        >✓</button>
                        <div class="crm-hw-phone-main">
                            <div class="crm-hw-phone-head">
                                <x-crm.person-name class="crm-hw-phone-name" :student-id="$student['id']" :name="$student['name']" />
                                @if ($student['link_opened'] ?? false)
                                    <span class="crm-hw-phone-opened">Opened {{ $student['link_opened_at'] ?? '' }}</span>
                                @endif
                            </div>
                            @if (($student['status_key'] ?? null) === 'not_done')
                                @php
                                    $parentNote = match ($student['parent_line'] ?? null) {
                                        'Message shared with parents' => 'WhatsApp sent',
                                        'Message was not shared' => 'WhatsApp not sent',
                                        'Message to parents is waiting' => 'WhatsApp waiting',
                                        default => 'Not done',
                                    };
                                @endphp
                                <p class="crm-hw-phone-meta">{{ $parentNote }}</p>
                            @endif
                            <div class="crm-hw-phone-actions">
                                    <button
                                        type="button"
                                        wire:click="markStudentNotChecked({{ $student['id'] }})"
                                        wire:loading.attr="disabled"
                                        @disabled(($student['status_key'] ?? null) === 'not_done')
                                        title="{{ ($student['status_key'] ?? null) === 'not_done' ? 'Not done is final.' : 'No mark yet' }}"
                                        wire:target="markStudentNotChecked({{ $student['id'] }}),markStudentDone({{ $student['id'] }}),askSingleNotDone({{ $student['id'] }}),confirmSingleNotDone"
                                        @class([
                                            'crm-hw-btn-wait',
                                            'is-on' => blank($student['status_key'] ?? null),
                                            'is-locked' => ($student['status_key'] ?? null) === 'not_done',
                                        ])
                                    >Not checked</button>
                                    <button
                                        type="button"
                                        wire:click="markStudentDone({{ $student['id'] }})"
                                        wire:loading.attr="disabled"
                                        @disabled(($student['status_key'] ?? null) === 'not_done')
                                        title="{{ ($student['status_key'] ?? null) === 'not_done' ? 'Not done is final.' : 'Mark done. No WhatsApp.' }}"
                                        wire:target="markStudentNotChecked({{ $student['id'] }}),markStudentDone({{ $student['id'] }}),askSingleNotDone({{ $student['id'] }}),confirmSingleNotDone"
                                        @class([
                                            'crm-hw-btn-done',
                                            'is-on' => ($student['status_key'] ?? null) === 'done',
                                            'is-locked' => ($student['status_key'] ?? null) === 'not_done',
                                        ])
                                    >Done</button>
                                    <button
                                        type="button"
                                        wire:click="askSingleNotDone({{ $student['id'] }})"
                                        wire:loading.attr="disabled"
                                        @disabled(($student['status_key'] ?? null) === 'not_done')
                                        title="{{ ($student['status_key'] ?? null) === 'not_done' ? 'Not done is final.' : 'Parents will get a WhatsApp.' }}"
                                        wire:target="markStudentNotChecked({{ $student['id'] }}),markStudentDone({{ $student['id'] }}),askSingleNotDone({{ $student['id'] }}),confirmSingleNotDone"
                                        @class([
                                            'crm-hw-btn-miss',
                                            'is-on' => ($student['status_key'] ?? null) === 'not_done',
                                        ])
                                    >Not done</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-gray-500">No students found for this class.</p>
                @endforelse
            </div>

            <x-crm.responsive-table class="crm-hw-roster crm-hw-roster-desktop border-t border-gray-100 dark:border-gray-800">
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
                                    @php $ticked = in_array((int) $student['id'], $selectedStudentIds ?? [], true); @endphp
                                    <div class="crm-hw-name">
                                        <button
                                            type="button"
                                            wire:click="toggleStudent({{ (int) $student['id'] }})"
                                            aria-label="Tick {{ $student['name'] }}"
                                            @class([
                                                'crm-hw-tick inline-flex items-center justify-center border font-bold',
                                                'border-primary-600 bg-primary-600 text-white' => $ticked,
                                                'border-gray-300 bg-white text-transparent dark:border-gray-500 dark:bg-gray-900' => ! $ticked,
                                            ])
                                        >✓</button>
                                        <x-crm.person-name :student-id="$student['id']" :name="$student['name']" />
                                    </div>
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
                                        <span @class([
                                            'font-semibold',
                                            'text-emerald-700 dark:text-emerald-300' => ($student['status_key'] ?? null) === 'done',
                                            'text-rose-700 dark:text-rose-300' => ($student['status_key'] ?? null) === 'not_done',
                                        ])>
                                            {{ $student['last_status'] }}
                                            @if (filled($student['parent_line'] ?? null))
                                                · {{ $student['parent_line'] }}
                                            @endif
                                        </span>
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
                                        <span class="font-semibold text-gray-500">Not checked</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right crm-responsive-table__actions" data-label="">
                                    <div class="inline-flex overflow-hidden rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                                        <button
                                            type="button"
                                            wire:click="markStudentNotChecked({{ $student['id'] }})"
                                            wire:loading.attr="disabled"
                                            @disabled(($student['status_key'] ?? null) === 'not_done')
                                            title="{{ ($student['status_key'] ?? null) === 'not_done' ? 'Not done is final.' : 'No mark yet' }}"
                                            wire:target="markStudentNotChecked({{ $student['id'] }}),markStudentDone({{ $student['id'] }}),askSingleNotDone({{ $student['id'] }}),confirmSingleNotDone"
                                            @class([
                                                'h-8 px-2.5 text-xs font-semibold',
                                                'bg-gray-700 text-white' => blank($student['status_key'] ?? null),
                                                'cursor-not-allowed bg-gray-100 text-gray-400' => ($student['status_key'] ?? null) === 'not_done',
                                                'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300' => filled($student['status_key'] ?? null) && ($student['status_key'] ?? null) !== 'not_done',
                                            ])
                                        >
                                            Not checked
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="markStudentDone({{ $student['id'] }})"
                                            wire:loading.attr="disabled"
                                            @disabled(($student['status_key'] ?? null) === 'not_done')
                                            title="{{ ($student['status_key'] ?? null) === 'not_done' ? 'Not done is final.' : 'Mark done. No WhatsApp.' }}"
                                            wire:target="markStudentNotChecked({{ $student['id'] }}),markStudentDone({{ $student['id'] }}),askSingleNotDone({{ $student['id'] }}),confirmSingleNotDone"
                                            @class([
                                                'h-8 px-2.5 text-xs font-semibold',
                                                'bg-emerald-600 text-white' => ($student['status_key'] ?? null) === 'done',
                                                'cursor-not-allowed bg-gray-100 text-gray-400' => ($student['status_key'] ?? null) === 'not_done',
                                                'bg-white text-gray-600 hover:bg-emerald-50 dark:bg-gray-900 dark:text-gray-300' => ($student['status_key'] ?? null) !== 'done' && ($student['status_key'] ?? null) !== 'not_done',
                                            ])
                                        >
                                            Done
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="askSingleNotDone({{ $student['id'] }})"
                                            wire:loading.attr="disabled"
                                            @disabled(($student['status_key'] ?? null) === 'not_done')
                                            title="{{ ($student['status_key'] ?? null) === 'not_done' ? 'Not done is final.' : 'Parents will get a WhatsApp.' }}"
                                            wire:target="markStudentNotChecked({{ $student['id'] }}),markStudentDone({{ $student['id'] }}),askSingleNotDone({{ $student['id'] }}),confirmSingleNotDone"
                                            @class([
                                                'h-8 px-2.5 text-xs font-semibold',
                                                'bg-rose-600 text-white' => ($student['status_key'] ?? null) === 'not_done',
                                                'bg-white text-gray-600 hover:bg-rose-50 dark:bg-gray-900 dark:text-gray-300' => ($student['status_key'] ?? null) !== 'not_done',
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

        @if (($selectedCount ?? 0) > 0 || in_array(($bulkStep ?? ''), ['ask', 'confirm'], true) || is_array($singleNotDone ?? null))
            <style>
                .crm-hw-check-dock {
                    position: fixed;
                    z-index: 40;
                    left: 0.5rem;
                    right: 0.5rem;
                    bottom: 0.75rem;
                }
                @media (max-width: 1023px) {
                    .fi-body:has(.fi-mobile-bottom-nav) .crm-hw-check-dock {
                        bottom: calc(4.25rem + env(safe-area-inset-bottom) + 0.35rem);
                    }
                }
                @media (min-width: 1024px) {
                    .crm-hw-check-dock {
                        left: auto;
                        right: 1.25rem;
                        width: 24rem;
                    }
                }
            </style>
            <div class="crm-hw-check-dock">
                <div class="rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    @if (is_array($singleNotDone ?? null))
                        <p class="text-xs leading-5 text-gray-800 dark:text-gray-100">
                            @if ($singleNotDone['will_message'])
                                {{ $singleNotDone['name'] }} will be marked Not done. Their parent will get a WhatsApp. Other students stay not checked.
                            @elseif ($singleNotDone['already_shared'])
                                {{ $singleNotDone['name'] }} is already Not done. No new WhatsApp. Other students stay not checked.
                            @else
                                {{ $singleNotDone['name'] }} will be marked Not done. There is no mobile number, so no WhatsApp will go. Other students stay not checked.
                            @endif
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="confirmSingleNotDone"
                                wire:loading.attr="disabled"
                                wire:target="confirmSingleNotDone"
                                class="h-11 flex-1 rounded-lg bg-rose-600 px-2 text-sm font-semibold text-white disabled:opacity-70"
                            >
                                <span wire:loading.remove wire:target="confirmSingleNotDone">Send to parent</span>
                                <span wire:loading wire:target="confirmSingleNotDone">Saving…</span>
                            </button>
                            <button type="button" wire:click="cancelSingleNotDone" class="h-11 rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 dark:text-gray-200 dark:ring-white/20">Cancel</button>
                        </div>
                    @elseif (($bulkStep ?? '') === 'ask')
                        <p class="text-xs font-semibold text-gray-950 dark:text-white">Did these {{ $selectedCount }} do the homework?</p>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" wire:click="chooseBulk('not_done')" class="h-8 flex-1 rounded-lg bg-rose-600 px-2 text-xs font-semibold text-white">Not done</button>
                            <button type="button" wire:click="chooseBulk('done')" class="h-8 flex-1 rounded-lg bg-emerald-600 px-2 text-xs font-semibold text-white">Done</button>
                            <button type="button" wire:click="cancelBulk" class="h-8 rounded-lg px-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Cancel</button>
                        </div>
                    @elseif (($bulkStep ?? '') === 'confirm' && is_array($bulkSummary ?? null))
                        <p class="text-sm leading-5 text-gray-800 dark:text-gray-100">
                            @if (($bulkChoice ?? '') === 'not_checked')
                                These {{ $bulkSummary['ticked'] }} {{ $bulkSummary['ticked'] === 1 ? 'student' : 'students' }} will go back to Not checked. No WhatsApp. Students you did not tick stay as they are.
                            @elseif (($bulkChoice ?? '') === 'not_done')
                                These {{ $bulkSummary['not_done'] }} {{ $bulkSummary['not_done'] === 1 ? 'student' : 'students' }} will be marked Not done.
                                @if ($bulkSummary['messages'] > 0)
                                    Their parents will get a WhatsApp.
                                @else
                                    No new WhatsApp will go.
                                @endif
                                Students you did not tick stay not checked.
                            @else
                                These {{ $bulkSummary['done'] }} {{ $bulkSummary['done'] === 1 ? 'student' : 'students' }} will be marked Done. No WhatsApp goes to parents. Students you did not tick stay not checked.
                            @endif
                            @if ($bulkSummary['no_mobile'] > 0)
                                {{ $bulkSummary['no_mobile'] }} have no mobile.
                            @endif
                            @if (($bulkSummary['locked'] ?? 0) > 0)
                                {{ $bulkSummary['locked'] }} already Not done. That mark stays. Parents were already told.
                            @endif
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="confirmBulk"
                                wire:loading.attr="disabled"
                                wire:target="confirmBulk"
                                @class([
                                    'h-11 flex-1 rounded-lg px-2 text-sm font-semibold text-white disabled:opacity-70',
                                    'bg-rose-600' => ($bulkChoice ?? '') === 'not_done',
                                    'bg-gray-700' => ($bulkChoice ?? '') === 'not_checked',
                                    'bg-emerald-600' => ($bulkChoice ?? '') === 'done',
                                ])
                            >
                                <span wire:loading.remove wire:target="confirmBulk">{{ match ($bulkChoice ?? '') {
                                    'not_done' => 'Send to parents',
                                    'not_checked' => 'Set not checked',
                                    default => 'Mark done',
                                } }}</span>
                                <span wire:loading wire:target="confirmBulk">Saving…</span>
                            </button>
                            <button type="button" wire:click="cancelBulk" class="h-11 rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 dark:text-gray-200 dark:ring-white/20">Cancel</button>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <p class="min-w-0 flex-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $selectedCount }} selected</p>
                            <button type="button" wire:click="clearBulkSelection" class="h-11 rounded-lg px-2 text-sm font-semibold text-gray-600 dark:text-gray-300">Clear</button>
                            <button type="button" wire:click="chooseBulk('not_checked')" class="h-11 rounded-lg bg-gray-700 px-3 text-sm font-semibold text-white">Not checked</button>
                            <button type="button" wire:click="chooseBulk('done')" class="h-11 rounded-lg bg-emerald-600 px-3 text-sm font-semibold text-white">Mark done</button>
                            <button type="button" wire:click="chooseBulk('not_done')" class="h-11 rounded-lg bg-rose-600 px-3 text-sm font-semibold text-white">Mark not done</button>
                        </div>
                    @endif
                </div>
            </div>
            <div class="h-24"></div>
        @endif
    @endif
</div>
