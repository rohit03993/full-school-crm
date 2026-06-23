@php
    use App\Filament\Pages\StudentProfilePage;
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-3xl lg:pb-6">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['label' => 'Total', 'value' => $stats['total'] ?? 0],
            ['label' => 'Uncalled', 'value' => $stats['uncalled'] ?? 0],
            ['label' => 'Called', 'value' => $stats['called'] ?? 0],
            ['label' => 'Due calls', 'value' => $stats['due_call_followups'] ?? 0],
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
                @foreach (['all' => 'All', 'uncalled' => 'Uncalled', 'called' => 'Called'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('calledFilter', '{{ $value }}')"
                        @class([
                            'rounded-full px-3 py-1.5 text-xs font-semibold transition touch-manipulation',
                            'bg-primary-600 text-white shadow-sm' => $calledFilter === $value,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/15' => $calledFilter !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @if ($leads === null || $leads->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">No assigned leads</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                @if (filled($search) || $calledFilter !== 'all')
                    Try clearing filters or search a different term.
                @else
                    When admin assigns leads to you for calling, they appear here.
                @endif
            </p>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($leads as $enquiry)
                @php
                    $student = $enquiry->student;
                @endphp
                <a
                    href="{{ $student ? StudentProfilePage::getUrl(['record' => $student->id]) : '#' }}"
                    class="group flex w-full touch-manipulation items-center gap-3 rounded-xl border border-gray-200/80 bg-white p-3.5 shadow-sm transition hover:border-primary-400/60 hover:bg-primary-500/[0.03] hover:shadow-md active:scale-[0.99] dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-primary-500/50 sm:gap-4 sm:p-4"
                >
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500/15 to-primary-600/5 ring-1 ring-primary-500/10">
                        <x-filament::icon icon="heroicon-o-user" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="truncate text-base font-bold text-gray-950 dark:text-white">
                                {{ $student?->name ?? 'Unknown' }}
                            </p>
                            @if ($student && (int) $student->total_calls === 0)
                                <span class="inline-flex shrink-0 rounded-md bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-400">
                                    Uncalled
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-sm font-semibold tracking-wide text-primary-600 dark:text-primary-400">
                            {{ $student?->mobile ?? '—' }}
                        </p>

                        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ $enquiry->enquiry_number }}</span>
                            @if ($enquiry->course)
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span class="truncate">{{ $enquiry->course->name }}</span>
                            @endif
                            @if ($enquiry->latest_visit_status)
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span>{{ $enquiry->latest_visit_status->label() }}</span>
                            @endif
                        </div>

                        @if ($student)
                            @include('filament.pages.partials.student-last-call-summary', ['record' => $student, 'compact' => true])
                        @endif
                    </div>

                    <x-filament::icon
                        icon="heroicon-m-chevron-right"
                        class="h-5 w-5 shrink-0 text-gray-300 group-hover:text-primary-500 dark:text-gray-600"
                    />
                </a>
            @endforeach
        </div>

        @if ($leads->hasPages())
            <div class="pt-2">
                {{ $leads->links() }}
            </div>
        @endif
    @endif
</div>
