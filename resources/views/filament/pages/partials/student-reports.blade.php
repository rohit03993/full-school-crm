<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-4xl lg:pb-6">
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['label' => 'All reports', 'value' => $stats['total'] ?? 0],
            ['label' => 'With fine', 'value' => $stats['with_fine'] ?? 0],
            ['label' => 'Without fine', 'value' => $stats['without_fine'] ?? 0],
            ['label' => 'Fine total', 'value' => '₹'.number_format((int) ($stats['fine_amount'] ?? 0))],
        ] as $stat)
            <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white sm:text-2xl">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
        <div class="fi-crm-form grid gap-3 sm:grid-cols-2">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Student, staff, or what happened"
                class="fi-crm-input block min-h-11 w-full sm:col-span-2"
            />
            <x-crm.select wire:model.live="fineFilter" class="min-h-11 w-full">
                <option value="">All fines</option>
                <option value="fine">Fine charged</option>
                <option value="without">Without fine</option>
            </x-crm.select>
            <x-crm.select wire:model.live="typeFilter" class="min-h-11 w-full">
                <option value="">All types</option>
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-crm.select>
        </div>
    </div>

    @if ($reports->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-base font-semibold text-gray-950 dark:text-white">No reports match</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try another name, or clear the filters.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($reports as $report)
                <a
                    href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $report->student_id]) }}"
                    class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition hover:ring-rose-300 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-rose-400/40"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-bold text-gray-950 dark:text-white">{{ $report->student?->name ?? 'Student' }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $report->typeLabelList() }}</p>
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200' => $report->fine_charged,
                            'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => ! $report->fine_charged,
                        ])>{{ $report->fineSummary() }}</span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $report->note }}</p>
                    <p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ $report->recorded_on?->format('d M Y') }}
                        · Entered by {{ $report->recordedBy?->name ?: 'Unknown' }}
                    </p>
                </a>
            @endforeach
        </div>

        @if ($reports->hasPages())
            <div class="pt-2">
                {{ $reports->links() }}
            </div>
        @endif
    @endif
</div>
