@php
    use App\Filament\Pages\MyLeadsPage;
    use App\Filament\Pages\StudentProfilePage;
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-3xl lg:pb-6">
    @if (($callStats['uncalled'] ?? 0) > 0 || ($callStats['due_call_followups'] ?? 0) > 0)
        <a
            href="{{ MyLeadsPage::getUrl() }}"
            class="flex items-center justify-between gap-3 rounded-xl border border-primary-200/80 bg-primary-50/80 px-4 py-3 shadow-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-primary-500/25 dark:bg-primary-500/10"
        >
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-primary-800 dark:text-primary-300">Assigned to call</p>
                <p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $callStats['uncalled'] ?? 0 }} uncalled lead{{ ($callStats['uncalled'] ?? 0) === 1 ? '' : 's' }}
                    @if (($callStats['due_call_followups'] ?? 0) > 0)
                        · {{ $callStats['due_call_followups'] }} due follow-up{{ ($callStats['due_call_followups'] ?? 0) === 1 ? '' : 's' }}
                    @endif
                </p>
            </div>
            <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
        </a>
    @endif

    <div class="space-y-4">
        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Campus meetings</p>
        <div class="grid grid-cols-3 gap-3">
        @foreach ([
            ['label' => 'Open', 'value' => $stats['open'] ?? 0],
            ['label' => 'Closed', 'value' => $stats['closed'] ?? 0],
            ['label' => 'Total', 'value' => $stats['total'] ?? 0],
        ] as $stat)
            <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-crm-form space-y-3">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search name, mobile, enquiry no."
                class="fi-crm-input block w-full"
            />

            <div class="flex flex-wrap gap-2">
                @foreach (['open' => 'Open', 'closed' => 'Closed'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('statusFilter', '{{ $value }}')"
                        @class([
                            'rounded-full px-3 py-1.5 text-xs font-semibold transition touch-manipulation',
                            'bg-primary-600 text-white shadow-sm' => $statusFilter === $value,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/15' => $statusFilter !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @if ($meetings === null || $meetings->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ $statusFilter === 'closed' ? 'No closed meetings yet' : 'No open meetings' }}
            </p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                @if (filled($search))
                    Try a different search term.
                @elseif ($statusFilter === 'closed')
                    Meetings you close with notes appear here for your record.
                @else
                    When reception assigns a campus visit to you, it appears here until you close the meeting.
                @endif
            </p>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($meetings as $assignment)
                @php
                    $student = $assignment->student;
                    $isClosed = $statusFilter === 'closed';
                @endphp
                <a
                    href="{{ $student ? StudentProfilePage::getUrl(['record' => $student->id]) : '#' }}"
                    @class([
                        'group flex w-full touch-manipulation items-start gap-3 rounded-xl border bg-white p-3.5 shadow-sm transition hover:shadow-md active:scale-[0.99] dark:bg-white/[0.03] sm:gap-4 sm:p-4',
                        'border-amber-200/80 hover:border-amber-400/60 hover:bg-amber-500/[0.03] dark:border-amber-500/20 dark:hover:border-amber-500/50' => ! $isClosed,
                        'border-gray-200/80 hover:border-gray-300 dark:border-white/10 dark:hover:border-white/20' => $isClosed,
                    ])
                >
                    <div @class([
                        'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ring-1',
                        'bg-gradient-to-br from-amber-500/15 to-amber-600/5 ring-amber-500/15' => ! $isClosed,
                        'bg-gray-100 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => $isClosed,
                    ])>
                        <x-filament::icon
                            :icon="$isClosed ? 'heroicon-o-check-circle' : 'heroicon-o-user-group'"
                            @class([
                                'h-5 w-5',
                                'text-amber-700 dark:text-amber-400' => ! $isClosed,
                                'text-emerald-600 dark:text-emerald-400' => $isClosed,
                            ])
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="truncate text-base font-bold text-gray-950 dark:text-white">
                                {{ $student?->name ?? 'Unknown' }}
                            </p>
                            @if ($isClosed)
                                <span class="inline-flex shrink-0 rounded-md bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-400">
                                    Closed
                                </span>
                            @elseif ($student?->activeEnrollment)
                                <span class="inline-flex shrink-0 rounded-md bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-400">
                                    Enrolled
                                </span>
                            @else
                                <span class="inline-flex shrink-0 rounded-md bg-sky-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-sky-700 ring-1 ring-sky-500/20 dark:text-sky-400">
                                    Lead
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-sm font-semibold tracking-wide text-primary-600 dark:text-primary-400">
                            {{ $student?->mobile ?? '—' }}
                        </p>

                        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @if ($assignment->enquiry)
                                <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ $assignment->enquiry->enquiry_number }}</span>
                            @endif
                            @if ($assignment->enquiry?->course)
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span class="truncate">{{ $assignment->enquiry->course->name }}</span>
                            @endif
                            <span class="text-gray-300 dark:text-gray-600">·</span>
                            <span>From {{ $assignment->assignedBy?->name ?? 'Staff' }}</span>
                            <span class="text-gray-300 dark:text-gray-600">·</span>
                            <span>
                                {{ $isClosed
                                    ? $assignment->closed_at?->format('d M Y H:i')
                                    : $assignment->created_at?->format('d M Y H:i') }}
                            </span>
                        </div>

                        @if ($isClosed && filled($assignment->meeting_notes))
                            <p class="mt-2 line-clamp-3 text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-semibold">Notes:</span> {{ $assignment->meeting_notes }}
                            </p>
                        @elseif (filled($assignment->handoff_notes))
                            <p class="mt-2 line-clamp-2 text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-semibold">Handoff:</span> {{ $assignment->handoff_notes }}
                            </p>
                        @endif
                    </div>

                    <x-filament::icon
                        icon="heroicon-m-chevron-right"
                        class="mt-1 h-5 w-5 shrink-0 text-gray-300 transition group-hover:text-primary-600 dark:text-gray-600 dark:group-hover:text-primary-400"
                    />
                </a>
            @endforeach
        </div>

        @if ($meetings->hasPages())
            <div class="pt-2">
                {{ $meetings->links() }}
            </div>
        @endif
    @endif
    </div>
</div>
