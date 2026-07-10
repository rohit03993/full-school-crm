@php
    use App\Filament\Pages\StudentProfilePage;
@endphp

<div @class([
    'space-y-4',
    'mx-auto max-w-lg pb-24 lg:max-w-3xl lg:pb-6' => ! ($embedded ?? false),
])>
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
                wire:model.live.debounce.300ms="myCaseSearch"
                placeholder="Search case no., student, title…"
                class="fi-crm-input block w-full"
            />

            <div class="flex flex-wrap gap-2">
                @foreach (['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('myCaseStatusFilter', '{{ $value }}')"
                        @class([
                            'rounded-full px-3 py-1.5 text-xs font-semibold transition touch-manipulation',
                            'bg-primary-600 text-white shadow-sm' => $myCaseStatusFilter === $value,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/15' => $myCaseStatusFilter !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <x-crm.select wire:model.live="myCaseTypeFilter" class="w-full">
                <option value="">All case types</option>
                @foreach ($caseTypeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-crm.select>
        </div>
    </div>

    @if ($cases === null || $cases->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">No cases assigned to you</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                @if (filled($myCaseSearch) || $myCaseStatusFilter !== 'open' || filled($myCaseTypeFilter))
                    Try clearing filters.
                @else
                    When a counselor opens a case and assigns it to you, it appears here for leads and enrolled students.
                @endif
            </p>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($cases as $case)
                @php
                    $student = $case->student;
                    $latestNote = $case->assignments->first()?->note;
                @endphp
                <a
                    href="{{ $student ? StudentProfilePage::getUrl(['record' => $student->id, 'tab' => 'cases', 'case' => $case->id]) : '#' }}"
                    class="group flex w-full touch-manipulation flex-col gap-2 rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm transition hover:border-primary-400/60 hover:bg-primary-500/[0.03] hover:shadow-md dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-primary-500/50"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400">{{ $case->case_number }}</span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                    {{ $case->case_type->label() }}
                                </span>
                                @if ($case->isOpen())
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Open</span>
                                @endif
                            </div>
                            <p class="mt-1 text-base font-bold text-gray-950 dark:text-white">{{ $case->title }}</p>
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                                {{ $student?->name ?? 'Student' }}
                                @if ($student?->mobile)
                                    · {{ $student->mobile }}
                                @endif
                            </p>
                        </div>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="mt-1 h-5 w-5 shrink-0 text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" />
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Opened {{ $case->opened_at?->format('d M Y') }} by {{ $case->openedBy?->name ?? 'Staff' }}
                    </p>
                    @if ($latestNote)
                        <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                            Handoff: {{ \Illuminate\Support\Str::limit($latestNote, 140) }}
                        </p>
                    @endif
                </a>
            @endforeach
        </div>

        @if ($cases->hasPages())
            <div class="pt-2">
                {{ $cases->links() }}
            </div>
        @endif
    @endif
</div>
